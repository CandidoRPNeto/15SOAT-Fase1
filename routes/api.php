<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\ItemController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\ServiceOrderController;
use App\Http\Controllers\Api\V1\ServiceOrderHistoryController;
use App\Http\Controllers\Api\V1\VehicleController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Auth — público
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Auth — autenticado. client_jwt (Fase 3, auth por CPF via Function
    // Serverless) é aditivo ao Sanctum — não substitui o login
    // email+senha existente. Ver docs/architecture/rfcs/rfc-003-cpf-auth-strategy.md.
    Route::middleware('auth:sanctum,client_jwt')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Fase 4: alvo do ForwardAuth do Traefik (ADR-013) — Billing e
        // Execution recebem a identidade nos headers X-User-*.
        Route::get('/auth/verify', [AuthController::class, 'verify']);

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

        // Itens (insumos e peças) — receptionist e mechanic
        Route::middleware('role:receptionist,mechanic')->group(function () {
            Route::apiResource('items', ItemController::class);
        });

        // Ordens de Serviço
        Route::prefix('service-orders')->group(function () {
            // Listagem e detalhe — todos os perfis autenticados
            Route::get('/', [ServiceOrderController::class, 'index']);
            Route::get('/{id}/status', [ServiceOrderController::class, 'status']);
            Route::get('/{id}/history', [ServiceOrderHistoryController::class, 'show']);
            Route::get('/{id}', [ServiceOrderController::class, 'show']);

            // Criação — receptionist e mechanic
            Route::middleware('role:receptionist,mechanic')
                ->post('/', [ServiceOrderController::class, 'store']);

            // Adicionar/remover serviço e item durante o diagnóstico — mechanic.
            // Fase 4: orçamento, aprovação e pagamento vivem no Billing
            // Service; início/fim de diagnóstico e reparo no Execution
            // Service (RFC-004). A OS muda de status pelos eventos da saga.
            Route::middleware('role:mechanic')->group(function () {
                Route::post('/{id}/services', [ServiceOrderController::class, 'addService']);
                Route::post('/{id}/items', [ServiceOrderController::class, 'addItem']);
                Route::delete('/{id}/items/{itemId}', [ServiceOrderController::class, 'removeItem']);
            });

            // Entrega — receptionist
            Route::middleware('role:receptionist')
                ->post('/{id}/deliver', [ServiceOrderController::class, 'deliver']);
        });
    });
});
