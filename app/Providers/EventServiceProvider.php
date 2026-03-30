<?php

namespace App\Providers;

use App\Events\PaymentSuccessful;
use App\Listeners\LogPaymentSuccessful;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        PaymentSuccessful::class => [
            LogPaymentSuccessful::class,
        ],
    ];
}

