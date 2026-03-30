<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasUuids;

    protected $table = 'payments';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'transaction_id',
        'provider',
        'provider_reference',
        'status',
        'amount',
        'currency',
        'idempotency_key',
        'provider_payload',
    ];

    protected $casts = [
        'provider_payload' => 'array',
        'amount' => 'decimal:2',
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}

