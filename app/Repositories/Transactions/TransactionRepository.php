<?php

namespace App\Repositories\Transactions;

use App\Models\Transaction;

class TransactionRepository
{
    public function findByReference(string $reference): ?Transaction
    {
        return Transaction::query()->where('reference', $reference)->first();
    }

    public function findByUserAndIdempotencyKey(int $userId, string $idempotencyKey): ?Transaction
    {
        return Transaction::query()
            ->where('user_id', $userId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    public function createPending(array $attributes): Transaction
    {
        $attributes['status'] = Transaction::STATUS_PENDING;

        return Transaction::query()->create($attributes);
    }

    public function updateStatus(Transaction $transaction, string $toStatus, array $attributes = []): Transaction
    {
        $transaction->status = $toStatus;
        $transaction->fill($attributes);
        $transaction->save();

        return $transaction->refresh();
    }
}

