<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Http\Requests\Webhooks\PaystackWebhookRequest;
use App\Services\Payments\PaymentWebhookService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class WebhookController extends Controller
{
    public function handlePaystack(PaystackWebhookRequest $request, PaymentWebhookService $service): JsonResponse
    {
        try {
            $payload = $request->all();

            $result = $service->handlePaystackWebhook($payload);

            return ApiResponse::success(
                data: [
                    'transaction_id' => $result['transaction']->id,
                    'transaction_reference' => $result['transaction']->reference,
                    'status' => $result['transaction']->status,
                ],
                message: 'Webhook processed'
            );
        } catch (\Throwable $e) {
            return ApiResponse::error(
                message: 'Webhook processing failed',
                errors: ['detail' => $e->getMessage()],
                statusCode: 400
            );
        }
    }
}

