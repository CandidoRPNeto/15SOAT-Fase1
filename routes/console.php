<?php

use App\Jobs\SendFineNotificationJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Verifica OS finalizadas há mais de 24h sem retirada e notifica o cliente
Schedule::job(new SendFineNotificationJob)->hourly();
