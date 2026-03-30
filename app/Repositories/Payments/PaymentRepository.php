<?php

namespace App\Repositories\Payments;

use App\Models\Payment;

class PaymentRepository
{
    public function findLatestByTransactionId(string $transactionId): ?Payment
    {
        return Payment::query()
            ->where('transaction_id', $transactionId)
            ->orderByDesc('created_at')
            ->first();
    }

    public function createPending(array $attributes): Payment
    {
        $attributes['status'] = Payment::STATUS_PENDING;
        return Payment::query()->create($attributes);
    }

    public function updateStatus(Payment $payment, string $toStatus, array $attributes = []): Payment
    {
        $payment->status = $toStatus;
        $payment->fill($attributes);
        $payment->save();

        return $payment->refresh();
    }
}

