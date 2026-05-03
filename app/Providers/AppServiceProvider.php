<?php

namespace App\Providers;

use App\Contracts\MessagingServiceInterface;
use App\Contracts\PaymentServiceInterface;
use App\Services\StubMessagingService;
use App\Services\StubPaymentService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentServiceInterface::class, StubPaymentService::class);
        $this->app->bind(MessagingServiceInterface::class, StubMessagingService::class);
    }

    public function boot(): void
    {
        //
    }
}
