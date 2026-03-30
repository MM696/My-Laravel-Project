<?php

namespace App\Services\Payments;

use App\Events\PaymentSuccessful;
use App\Models\Payment;
use App\Models\Transaction;
use App\Repositories\AuditLogs\AuditLogRepository;
use App\Repositories\Payments\PaymentRepository;
use App\Repositories\Transactions\TransactionRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Carbon;

class PaymentWebhookService
{
    public function __construct(
        private readonly TransactionRepository $transactions,
        private readonly PaymentRepository $payments,
        private readonly AuditLogRepository $auditLogs
    ) {
    }

    /**
     * Handle webhook payload and update transaction/payment status.
     *
     * @return array{transaction: Transaction, payment: Payment}
     */
    public function handlePaystackWebhook(array $payload): array
    {
        $event = (string) ($payload['event'] ?? '');
        $reference = (string) (($payload['data']['reference'] ?? null) ?? '');

        if ($event === '' || $reference === '') {
            abort(422, 'Invalid webhook payload');
        }

        $transaction = $this->transactions->findByReference($reference);
        if (!$transaction) {
            abort(404, 'Transaction not found for webhook reference');
        }

        $toStatus = match ($event) {
            'charge.success' => Transaction::STATUS_SUCCESS,
            'charge.failed' => Transaction::STATUS_FAILED,
            default => Transaction::STATUS_FAILED,
        };

        // Idempotent webhook: no-op if already in that final status.
        if ($transaction->status === $toStatus) {
            $payment = $this->payments->findLatestByTransactionId($transaction->id)
                ?? $transaction->payments()->latest()->first();

            return ['transaction' => $transaction, 'payment' => $payment];
        }

        return DB::transaction(function () use ($transaction, $toStatus, $payload) {
            $fromStatus = $transaction->status;

            $updatedTransaction = $this->transactions->updateStatus($transaction, $toStatus, [
                'verified_at' => Carbon::now(),
                'provider_reference' => $payload['data']['reference'] ?? $transaction->provider_reference,
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'provider' => 'paystack',
                    'webhook' => $payload,
                ]),
            ]);

            $payment = $this->payments->findLatestByTransactionId($updatedTransaction->id);
            if (!$payment) {
                $payment = $this->payments->createPending([
                    'transaction_id' => $updatedTransaction->id,
                    'provider' => 'paystack',
                    'provider_reference' => $payload['data']['reference'] ?? null,
                    'status' => $toStatus,
                    'amount' => $updatedTransaction->amount,
                    'currency' => $updatedTransaction->currency,
                    'idempotency_key' => $updatedTransaction->idempotency_key,
                    'provider_payload' => $payload['data'] ?? $payload,
                ]);
            } else {
                $payment = $this->payments->updateStatus($payment, $toStatus, [
                    'provider_reference' => $payload['data']['reference'] ?? $payment->provider_reference,
                    'provider_payload' => $payload['data'] ?? $payload,
                ]);
            }

            $this->auditLogs->log(
                userId: $updatedTransaction->user_id,
                transactionId: $updatedTransaction->id,
                action: 'transaction.status_changed',
                fromStatus: $fromStatus,
                toStatus: $toStatus,
                context: [
                    'provider' => 'paystack',
                    'webhook_event' => $event,
                ]
            );

            if ($fromStatus !== $toStatus && $toStatus === Transaction::STATUS_SUCCESS) {
                Event::dispatch(new PaymentSuccessful($updatedTransaction, $payment));
            }

            return ['transaction' => $updatedTransaction, 'payment' => $payment];
        });
    }
}

