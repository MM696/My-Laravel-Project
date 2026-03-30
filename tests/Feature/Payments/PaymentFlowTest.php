<?php

namespace Tests\Feature\Payments;

use App\Models\Transaction;
use App\Models\User;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    public function test_initiate_payment_is_idempotent(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $idempotencyKey = 'idem_' . uniqid('', true);

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Idempotency-Key' => $idempotencyKey,
        ];

        $payload = [
            'amount' => 199.50,
            'currency' => 'NGN',
            'customer_email' => $user->email,
        ];

        $first = $this->withHeaders($headers)->postJson('/api/payments', $payload);
        $first->assertStatus(201);
        $first->assertJsonPath('success', true);

        $firstRef = $first->json('data.transaction_reference');
        $this->assertNotEmpty($firstRef);
        $this->assertDatabaseHas('transactions', [
            'reference' => $firstRef,
            'status' => Transaction::STATUS_PENDING,
        ]);

        $second = $this->withHeaders($headers)->postJson('/api/payments', $payload);
        $second->assertStatus(200);

        $secondRef = $second->json('data.transaction_reference');
        $this->assertSame($firstRef, $secondRef);

        $this->assertEquals(1, Transaction::query()->count());
    }

    public function test_verify_payment_updates_status_via_async_job(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $idempotencyKey = 'idem_' . uniqid('', true);

        $initPayload = [
            'amount' => 100.00,
            'currency' => 'NGN',
            'customer_email' => $user->email,
        ];

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Idempotency-Key' => $idempotencyKey,
        ];

        $init = $this->withHeaders($headers)->postJson('/api/payments', $initPayload);
        $init->assertStatus(201);

        $reference = $init->json('data.transaction_reference');
        $this->assertNotEmpty($reference);

        $verify = $this->withHeaders(['Authorization' => 'Bearer ' . $token])->postJson("/api/payments/{$reference}/verify", []);
        $verify->assertStatus(202);

        $expected = ((crc32($reference) % 2) === 0) ? Transaction::STATUS_SUCCESS : Transaction::STATUS_FAILED;

        $this->assertDatabaseHas('transactions', [
            'reference' => $reference,
            'status' => $expected,
        ]);
    }

    public function test_webhook_marks_transaction_success_and_writes_audit_log(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $idempotencyKey = 'idem_' . uniqid('', true);

        $init = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson('/api/payments', [
            'amount' => 10.00,
            'currency' => 'NGN',
            'customer_email' => $user->email,
        ]);

        $init->assertStatus(201);

        $reference = $init->json('data.transaction_reference');
        $transactionId = $init->json('data.transaction_id');
        $this->assertNotEmpty($reference);

        $payload = [
            'event' => 'charge.success',
            'data' => ['reference' => $reference],
        ];

        $payloadJson = json_encode($payload);
        $secret = config('paystack.webhook_secret');
        $signature = hash_hmac('sha512', $payloadJson, $secret);

        $webhook = $this->withHeaders([
            'X-Paystack-Signature' => $signature,
            'Content-Type' => 'application/json',
        ])->postJson('/api/webhooks/paystack', $payload);

        $webhook->assertStatus(200);

        $this->assertDatabaseHas('transactions', [
            'id' => $transactionId,
            'status' => Transaction::STATUS_SUCCESS,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'transaction_id' => $transactionId,
            'action' => 'payment.successful',
        ]);
    }
}

