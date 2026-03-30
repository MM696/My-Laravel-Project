<?php

namespace App\Listeners;

use App\Events\PaymentSuccessful;
use App\Repositories\AuditLogs\AuditLogRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LogPaymentSuccessful implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        private readonly AuditLogRepository $auditLogs
    ) {
    }

    public function handle(PaymentSuccessful $event): void
    {
        $this->auditLogs->log(
            userId: $event->transaction->user_id,
            transactionId: $event->transaction->id,
            action: 'payment.successful',
            fromStatus: $event->transaction->status,
            toStatus: $event->transaction->status,
            context: [
                'provider' => $event->payment->provider,
                'provider_reference' => $event->payment->provider_reference,
                'payment_id' => $event->payment->id,
            ]
        );
    }
}

