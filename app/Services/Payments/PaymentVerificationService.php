<?php

namespace App\Services\Payments;

use App\Events\PaymentSuccessful;
use App\Models\Payment;
use App\Models\Transaction;
use App\Repositories\AuditLogs\AuditLogRepository;
use App\Repositories\Payments\PaymentRepository;
use App\Repositories\Transactions\TransactionRepository;
use App\Support\Paystack\PaystackSimulator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PaymentVerificationService
{
    public function __construct(
        private readonly TransactionRepository $transactions,
        private readonly PaymentRepository $payments,
        private readonly AuditLogRepository $auditLogs,
        private readonly PaystackSimulator $paystack
    ) {
    }

    /**
     * @return array{transaction: Transaction, payment: Payment}
     */
    public function verify(int $userId, string $transactionReference): array
    {
        $transaction = $this->transactions->findByReference($transactionReference);

        if (!$transaction || $transaction->user_id !== $userId) {
            throw new ModelNotFoundException('Transaction not found');
        }

        // Idempotent verification: if already final, return current records.
        if (in_array($transaction->status, [Transaction::STATUS_SUCCESS, Transaction::STATUS_FAILED], true)) {
            $payment = $this->payments->findLatestByTransactionId($transaction->id)
                ?? $transaction->payments()->latest()->first();

            return ['transaction' => $transaction, 'payment' => $payment];
        }

        return DB::transaction(function () use ($transaction) {
            $fromStatus = $transaction->status;

            $sim = $this->paystack->verifyPayment(
                transactionReference: $transaction->reference,
                currency: $transaction->currency,
                amount: (string) $transaction->amount
            );

            $toStatus = match ($sim['status']) {
                'success' => Transaction::STATUS_SUCCESS,
                default => Transaction::STATUS_FAILED,
            };

            // Update transaction (atomic state change).
            $updatedTransaction = $this->transactions->updateStatus($transaction, $toStatus, [
                'provider_reference' => $sim['provider_reference'] ?? $transaction->provider_reference,
                'verified_at' => Carbon::now(),
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'provider' => 'paystack',
                    'verification' => $sim['payload'] ?? null,
                ]),
            ]);

            $payment = $this->payments->findLatestByTransactionId($updatedTransaction->id);
            if (!$payment) {
                // Should not happen (initiate creates it), but keep the system robust.
                $payment = $this->payments->createPending([
                    'transaction_id' => $updatedTransaction->id,
                    'provider' => 'paystack',
                    'provider_reference' => $sim['provider_reference'] ?? null,
                    'status' => $toStatus,
                    'amount' => $updatedTransaction->amount,
                    'currency' => $updatedTransaction->currency,
                    'idempotency_key' => $updatedTransaction->idempotency_key,
                    'provider_payload' => $sim['payload'] ?? null,
                ]);
            } else {
                $payment = $this->payments->updateStatus($payment, $toStatus, [
                    'provider_reference' => $sim['provider_reference'] ?? $payment->provider_reference,
                    'provider_payload' => $sim['payload'] ?? $payment->provider_payload,
                ]);
            }

            // Audit status change.
            $this->auditLogs->log(
                userId: $updatedTransaction->user_id,
                transactionId: $updatedTransaction->id,
                action: 'transaction.status_changed',
                fromStatus: $fromStatus,
                toStatus: $toStatus,
                context: [
                    'provider' => 'paystack',
                    'provider_reference' => $sim['provider_reference'] ?? null,
                ]
            );

            // Event/Listener only when transitioning to success.
            if ($fromStatus !== $toStatus && $toStatus === Transaction::STATUS_SUCCESS) {
                Event::dispatch(new PaymentSuccessful($updatedTransaction, $payment));
            }

            return ['transaction' => $updatedTransaction, 'payment' => $payment];
        });
    }
}

