<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

class InitiatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'idempotency_key' => $this->header('Idempotency-Key'),
        ]);
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'customer_email' => ['required', 'email:rfc,dns'],
            'idempotency_key' => ['required', 'string', 'min:10', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return [
            'idempotency_key.required' => 'Idempotency-Key header is required.',
        ];
    }
}

