<?php

use App\Http\Controllers\Api\Payments\PaymentController;
use App\Http\Controllers\Api\Webhooks\WebhookController;
use App\Http\Middleware\EnsureHttps;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'throttle:api', EnsureHttps::class])
    ->group(function () {
        Route::get('/health', function () {
            return response()->json(['success' => true, 'message' => 'OK']);
        });

        Route::middleware(['auth:sanctum'])
            ->group(function () {
                Route::post('/payments', [PaymentController::class, 'initiate']);
                Route::post('/payments/{transaction_reference}/verify', [PaymentController::class, 'verify']);
            });

        Route::post('/webhooks/paystack', [WebhookController::class, 'handlePaystack']);
    });

