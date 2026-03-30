# Fintech Payment REST API (Laravel 13)

Production-ready REST backend for a fintech payment system using:
- Laravel latest (Laravel 13)
- Laravel Sanctum authentication (`auth:sanctum`)
- Layered architecture: Controllers -> Services -> Repositories
- Form Requests for validation
- Idempotent payment initiation (prevents duplicate payments)
- DB transactions for atomic operations
- Webhook handling + async verification via Redis queues
- Event/Listener for `PaymentSuccessful`
- Audit log of all transaction status changes
- Standardized API responses

> Note: This repo contains the payment-system implementation files. You should place these files inside a real Laravel 13 project (or copy `app/`, `routes/`, `database/migrations/`, `tests/`, and `config/` into one).

## Architecture (high level)

- `app/Http/Controllers/Api/...`  
  Request/response orchestration only.
- `app/Services/...`  
  Business logic (initiate/verify/webhook) and orchestration of domain rules.
- `app/Repositories/...`  
  DB access via Eloquent models.
- `app/Http/Requests/...`  
  Validation and (for webhooks) signature checking.
- `app/Jobs/...`  
  Async verification using Redis queue.
- `app/Events/...` + `app/Listeners/...`  
  `PaymentSuccessful` event handling.
- `app/Models/...`  
  Eloquent models (UUID transactions).
- `app/Support/Api/ApiResponse.php`  
  Consistent JSON response format.

## Environment variables

Create `.env` from `.env.example` and set:
- `PAYSTACK_SECRET_KEY`
- `PAYSTACK_WEBHOOK_SECRET`
- `QUEUE_CONNECTION=redis`
- Redis connection variables

HTTPS enforcement:
- `APP_FORCE_HTTPS=true` (middleware is included; register it in your HTTP kernel)

## Run locally

1. Create a fresh Laravel project:
   - `composer create-project laravel/laravel:^13 fintech-payment-api`
2. Copy these directories/files into the new Laravel project:
   - `composer.json` (dependencies)
   - `app/` (all payment code)
   - `config/` (paystack config)
   - `routes/`
   - `database/migrations/`
   - `tests/`
3. Install dependencies:
   - `composer install`
4. Generate app key:
   - `php artisan key:generate`
5. Run migrations:
   - `php artisan migrate`
6. Start the server:
   - `php artisan serve`

## API endpoints

All JSON responses follow:
```json
{
  "success": true,
  "message": "....",
  "data": { }
}
```

### Initiate payment (idempotent)

`POST /api/payments`

Headers:
- `Authorization: Bearer <sanctum-token>`
- `Idempotency-Key: <unique-key-per-payment-request>`

Body:
```json
{
  "amount": 199.50,
  "currency": "NGN",
  "customer_email": "customer@example.com"
}
```

Response `201` (created) or `200` (idempotent replay):
```json
{
  "success": true,
  "message": "Payment initiated",
  "data": {
    "transaction_id": "uuid",
    "transaction_reference": "TRX_...",
    "status": "pending"
  }
}
```

### Verify payment (async)

`POST /api/payments/{transaction_reference}/verify`

Response `202`:
```json
{
  "success": true,
  "message": "Verification queued",
  "data": { "status": "pending" }
}
```

Verification job will update:
- `transactions.status`
- `payments.status`
- `audit_logs` (status changes)

### Payment webhook (Paystack-style)

`POST /api/webhooks/paystack`

No authentication required. Must include signature:
- `X-Paystack-Signature: <hmac>`

Body example:
```json
{
  "event": "charge.success",
  "data": {
    "reference": "TRX_..."
  }
}
```

## Testing

Run:
- `php artisan test`

Included feature tests cover:
- initiate payment
- idempotency behavior
- verify endpoint queuing
- webhook status update

## Security checklist

- API keys only in `.env` (read via `config/paystack.php`)
- Input validation via Form Requests
- Idempotency enforced at DB level (unique index) + service logic
- DB transactions for atomic state changes
- HTTPS enforced via middleware (register it in `app/Http/Kernel.php`)

