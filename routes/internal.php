<?php

use App\Http\Controllers\Api\V1\InternalController;
use Illuminate\Support\Facades\Route;

Route::post('/clients/cpf-lookup', [InternalController::class, 'cpfLookup']);
