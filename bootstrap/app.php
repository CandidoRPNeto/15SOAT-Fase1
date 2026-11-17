<?php

use App\Http\Middleware\AssignCorrelationId;
use App\Http\Middleware\EnsureInternalApiKey;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\LogRequestLatency;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('api')
                ->prefix('webhook')
                ->group(base_path('routes/webhook.php'));

            // Fase 3 — só a Function Serverless de auth chama isto (ver
            // routes/internal.php). Fora de /api/v1 de propósito: não é
            // uma rota de negócio, e EnsureInternalApiKey substitui
            // Sanctum aqui.
            Route::middleware(['api', EnsureInternalApiKey::class])
                ->prefix('internal')
                ->group(base_path('routes/internal.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureRole::class,
        ]);

        $middleware->statefulApi();

        // Roda antes de tudo, pra todo log da requisição (inclusive de
        // middlewares abaixo) carregar o correlation id — RF de "logs
        // estruturados, incluindo correlação entre requisições"
        // (evolucao_fase3, Fase 3).
        $middleware->prepend(AssignCorrelationId::class);

        // Prepend depois de AssignCorrelationId = fica mais externo (mede
        // a requisição inteira, inclusive o tempo de AssignCorrelationId)
        // — RF de "latência das APIs" nos logs estruturados (Epic 6).
        $middleware->prepend(LogRequestLatency::class);

        // Fase 3: atrás do Traefik do Dokploy (Gateway), cujo IP interno
        // não é fixo/conhecido de antemão — confiar em todos os proxies é
        // o padrão recomendado quando a app só é alcançável através do
        // proxy (nunca diretamente da internet). Sem isso, $request->ip(),
        // url()->to() e a detecção de HTTPS ficam errados atrás do
        // Gateway. Implementação do Epic 4 (backlog.md), sem ADR própria —
        // é o default recomendado pela própria doc do Laravel pra deploy
        // atrás de proxy de IP não fixo.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $_, Request $request) {
            if ($request->expectsJson() || $request->is('api/*') || $request->is('webhook/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
        });
    })->create();
