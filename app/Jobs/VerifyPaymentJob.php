<?php

namespace App\Jobs;

use App\Services\Payments\PaymentVerificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class VerifyPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $connection = 'redis';
    public string $queue = 'payments';

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        public readonly int $userId,
        public readonly string $transactionReference
    ) {
    }

    public function handle(PaymentVerificationService $service): void
    {
        $service->verify(
            userId: $this->userId,
            transactionReference: $this->transactionReference
        );
    }
}

