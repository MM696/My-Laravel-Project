<?php

namespace App\Support\Paystack;

use Illuminate\Support\Str;

/**
 * Simulator for Paystack verification/webhook payloads.
 * This allows the API to be fully implemented/tested without an external dependency.
 */
class PaystackSimulator
{
    /**
     * Deterministic "verification" based on the transaction reference.
     * Even CRC32 => success, odd => failed.
     */
    public function verifyPayment(string $transactionReference, string $currency, string $amount): array
    {
        $crc = crc32($transactionReference);
        $isSuccess = ($crc % 2) === 0;

        $providerStatus = $isSuccess ? 'success' : 'failed';

        return [
            'status' => $providerStatus,
            'reference' => $transactionReference,
            'provider_reference' => 'ps_' . Str::upper(Str::random(10)),
            'payload' => [
                'amount' => $amount,
                'currency' => $currency,
                'status' => $providerStatus,
                'verified' => true,
            ],
        ];
    }
}

