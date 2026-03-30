<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('reference')->unique();
            $table->string('provider_reference')->nullable()->index();

            // pending | success | failed
            $table->string('status')->index()->default('pending');

            $table->decimal('amount', 18, 2);
            $table->string('currency', 3)->index();

            // Used to prevent duplicate initiation requests.
            $table->string('idempotency_key', 120);

            $table->json('metadata')->nullable();
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'idempotency_key'], 'transactions_user_id_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};

