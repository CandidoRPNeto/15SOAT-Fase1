<?php

namespace App\Providers;

use App\Application\Ports\ServiceOrderRepository;
use App\Contracts\EmailStatusUpdateServiceInterface;
use App\Contracts\MessagingServiceInterface;
use App\Contracts\PaymentServiceInterface;
use App\Infrastructure\Messaging\StubEmailStatusUpdateService;
use App\Infrastructure\Persistence\Eloquent\EloquentServiceOrderRepository;
use App\Services\StubMessagingService;
use App\Services\StubPaymentService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentServiceInterface::class, StubPaymentService::class);
        $this->app->bind(MessagingServiceInterface::class, StubMessagingService::class);
        $this->app->bind(ServiceOrderRepository::class, EloquentServiceOrderRepository::class);
        $this->app->bind(EmailStatusUpdateServiceInterface::class, StubEmailStatusUpdateService::class);
    }

    public function boot(): void
    {
        //
    }
}
