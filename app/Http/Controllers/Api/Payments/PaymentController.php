<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\InitiatePaymentRequest;
use App\Http\Requests\Payments\VerifyPaymentRequest;
use App\Jobs\VerifyPaymentJob;
use App\Repositories\Transactions\TransactionRepository;
use App\Services\Payments\PaymentInitiationService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(
        private readonly TransactionRepository $transactions
    ) {
    }

    public function initiate(InitiatePaymentRequest $request, PaymentInitiationService $service): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            $result = $service->initiate(
                userId: $userId,
                amount: (float) $request->input('amount'),
                currency: (string) $request->input('currency'),
                customerEmail: (string) $request->input('customer_email'),
                idempotencyKey: (string) $request->input('idempotency_key')
            );

            $statusCode = $result['created'] ? 201 : 200;

            return ApiResponse::success(
                data: [
                    'transaction_id' => $result['transaction']->id,
                    'transaction_reference' => $result['transaction']->reference,
                    'status' => $result['transaction']->status,
                ],
                message: $result['created'] ? 'Payment initiated' : 'Payment already initiated (idempotent)',
                statusCode: $statusCode
            );
        } catch (\Throwable $e) {
            return ApiResponse::error(
                message: 'Unable to initiate payment',
                errors: ['detail' => $e->getMessage()],
                statusCode: 500
            );
        }
    }

    public function verify(
        string $transaction_reference,
        VerifyPaymentRequest $request
    ): JsonResponse {
        try {
            $userId = $request->user()->id;

            $transaction = $this->transactions->findByReference($transaction_reference);
            if (!$transaction || $transaction->user_id !== $userId) {
                return ApiResponse::error('Transaction not found', [], 404);
            }

            // Prevent queue spam: only queue when pending.
            if ($transaction->status !== 'pending') {
                return ApiResponse::success(
                    data: ['status' => $transaction->status],
                    message: 'Transaction already verified'
                );
            }

            VerifyPaymentJob::dispatch($userId, $transaction_reference)
                ->onConnection('redis')
                ->onQueue('payments');

            return ApiResponse::success(
                data: ['status' => $transaction->status],
                message: 'Verification queued',
                statusCode: 202
            );
        } catch (\Throwable $e) {
            return ApiResponse::error(
                message: 'Unable to verify payment',
                errors: ['detail' => $e->getMessage()],
                statusCode: 500
            );
        }
    }
}

