<?php

namespace App\Repositories\AuditLogs;

use App\Models\AuditLog;
use App\Models\Transaction;

class AuditLogRepository
{
    public function log(
        ?int $userId,
        ?string $transactionId,
        string $action,
        ?string $fromStatus,
        ?string $toStatus,
        array $context = [],
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): AuditLog {
        return AuditLog::query()->create([
            'user_id' => $userId,
            'transaction_id' => $transactionId,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'context' => $context,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }
}

