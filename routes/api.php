<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\PartController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\ServiceOrderController;
use App\Http\Controllers\Api\V1\VehicleController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Auth — público
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Auth — autenticado
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Estatísticas (receptionist + mechanic)
        Route::middleware('role:receptionist,mechanic')
            ->get('/service-orders/stats', [ServiceOrderController::class, 'stats']);

        // Clientes — receptionist e mechanic
        Route::middleware('role:receptionist,mechanic')->group(function () {
            Route::apiResource('clients', ClientController::class);
        });

        // Veículos — receptionist e mechanic
        Route::middleware('role:receptionist,mechanic')->group(function () {
            Route::apiResource('vehicles', VehicleController::class);
        });

        // Serviços — receptionist e mechanic
        Route::middleware('role:receptionist,mechanic')->group(function () {
            Route::apiResource('services', ServiceController::class);
        });

        // Peças — receptionist e mechanic
        Route::middleware('role:receptionist,mechanic')->group(function () {
            Route::apiResource('parts', PartController::class);
        });

        // Ordens de Serviço
        Route::prefix('service-orders')->group(function () {
            // Listagem e detalhe — todos os perfis autenticados
            Route::get('/', [ServiceOrderController::class, 'index']);
            Route::get('/{id}', [ServiceOrderController::class, 'show']);

            // Criação — receptionist e mechanic
            Route::middleware('role:receptionist,mechanic')
                ->post('/', [ServiceOrderController::class, 'store']);

            // Adicionar serviço/peça e gerar orçamento — mechanic
            Route::middleware('role:mechanic')->group(function () {
                Route::post('/{id}/services', [ServiceOrderController::class, 'addService']);
                Route::post('/{id}/parts', [ServiceOrderController::class, 'addPart']);
                Route::post('/{id}/generate-budget', [ServiceOrderController::class, 'generateBudget']);
                Route::post('/{id}/start-execution', [ServiceOrderController::class, 'startExecution']);
                Route::post('/{id}/finalize', [ServiceOrderController::class, 'finalize']);
            });

            // Aprovação, cancelamento e pagamento — client
            Route::middleware('role:client')->group(function () {
                Route::post('/{id}/approve', [ServiceOrderController::class, 'approve']);
                Route::post('/{id}/cancel', [ServiceOrderController::class, 'cancel']);
                Route::post('/{id}/pay', [ServiceOrderController::class, 'pay']);
            });

            // Entrega — receptionist
            Route::middleware('role:receptionist')
                ->post('/{id}/deliver', [ServiceOrderController::class, 'deliver']);
        });
    });
});
