<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Transaction;
use App\Repositories\AuditLogs\AuditLogRepository;
use App\Repositories\Payments\PaymentRepository;
use App\Repositories\Transactions\TransactionRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

class PaymentInitiationService
{
    public function __construct(
        private readonly TransactionRepository $transactions,
        private readonly PaymentRepository $payments,
        private readonly AuditLogRepository $auditLogs
    ) {
    }

    /**
     * Initiate an idempotent payment request.
     *
     * @return array{transaction: Transaction, payment: Payment, created: bool}
     */
    public function initiate(
        int $userId,
        float $amount,
        string $currency,
        string $customerEmail,
        string $idempotencyKey
    ): array {
        return DB::transaction(function () use ($userId, $amount, $currency, $customerEmail, $idempotencyKey) {
            $existing = $this->transactions->findByUserAndIdempotencyKey($userId, $idempotencyKey);
            if ($existing) {
                $payment = $this->payments->findLatestByTransactionId($existing->id)
                    ?? $existing->payments()->latest()->first();

                return [
                    'transaction' => $existing,
                    'payment' => $payment,
                    'created' => false,
                ];
            }

            try {
                $reference = $this->generateUniqueReference();

                $transaction = $this->transactions->createPending([
                    'user_id' => $userId,
                    'reference' => $reference,
                    'status' => Transaction::STATUS_PENDING,
                    'amount' => $amount,
                    'currency' => strtoupper($currency),
                    'idempotency_key' => $idempotencyKey,
                    'metadata' => [
                        'customer_email' => $customerEmail,
                    ],
                    'provider_reference' => null,
                ]);

                // Create an initial payment record linked to the transaction.
                $payment = $this->payments->createPending([
                    'transaction_id' => $transaction->id,
                    'provider' => 'paystack',
                    'provider_reference' => null,
                    'status' => Payment::STATUS_PENDING,
                    'amount' => $amount,
                    'currency' => strtoupper($currency),
                    'idempotency_key' => $idempotencyKey,
                    'provider_payload' => null,
                ]);

                $this->auditLogs->log(
                    userId: $userId,
                    transactionId: $transaction->id,
                    action: 'transaction.status_changed',
                    fromStatus: null,
                    toStatus: Transaction::STATUS_PENDING,
                    context: [
                        'reference' => $transaction->reference,
                        'payment_id' => $payment->id,
                    ]
                );

                return [
                    'transaction' => $transaction,
                    'payment' => $payment,
                    'created' => true,
                ];
            } catch (QueryException $e) {
                // Handle concurrent duplicate idempotency keys gracefully.
                $existingAfterFailure = $this->transactions->findByUserAndIdempotencyKey($userId, $idempotencyKey);
                if ($existingAfterFailure) {
                    $payment = $this->payments->findLatestByTransactionId($existingAfterFailure->id)
                        ?? $existingAfterFailure->payments()->latest()->first();

                    return [
                        'transaction' => $existingAfterFailure,
                        'payment' => $payment,
                        'created' => false,
                    ];
                }

                throw $e;
            }
        });
    }

    private function generateUniqueReference(): string
    {
        // Reference uniqueness is enforced at DB-level; this loop makes collisions extremely unlikely.
        for ($i = 0; $i < 5; $i++) {
            $candidate = 'TRX_' . Str::upper(Str::random(18));
            if (!Transaction::query()->where('reference', $candidate)->exists()) {
                return $candidate;
            }
        }

        // Last resort: DB unique constraint should protect against duplicates, but we must still return something.
        return 'TRX_' . Str::upper(Str::random(18));
    }
}

