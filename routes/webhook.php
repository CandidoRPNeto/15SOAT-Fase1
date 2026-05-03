<?php

use App\Http\Controllers\Api\V1\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/messaging', [WebhookController::class, 'messaging']);
