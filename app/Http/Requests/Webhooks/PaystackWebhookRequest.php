<?php

namespace App\Http\Requests\Webhooks;

use Illuminate\Foundation\Http\FormRequest;

class PaystackWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        $secret = config('paystack.webhook_secret');
        if (!$secret) {
            // Secret is mandatory in production for webhook authenticity.
            return false;
        }

        $signature = (string) $this->header('X-Paystack-Signature');
        if ($signature === '') {
            return false;
        }

        $payload = (string) $this->getContent();
        $computed = hash_hmac('sha512', $payload, $secret);

        return hash_equals($computed, $signature);
    }

    public function rules(): array
    {
        return [
            'event' => ['required', 'string', 'in:charge.success,charge.failed'],
            'data' => ['required', 'array'],
            'data.reference' => ['required', 'string', 'max:255'],
        ];
    }
}

