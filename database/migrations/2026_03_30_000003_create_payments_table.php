<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('transaction_id')->constrained('transactions')->cascadeOnDelete();

            $table->string('provider')->default('paystack')->index();
            $table->string('provider_reference')->index();

            $table->string('status')->index()->default('pending');

            $table->decimal('amount', 18, 2);
            $table->string('currency', 3)->index();

            // Mirrors transaction idempotency key to ensure duplicate payment attempts can't be inserted.
            $table->string('idempotency_key', 120);

            $table->json('provider_payload')->nullable();

            $table->timestamps();

            $table->unique(['idempotency_key', 'provider'], 'payments_idempotency_provider_unique');
            $table->index(['transaction_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

