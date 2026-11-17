<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Propaga um id de correlação por requisição em todos os logs, e o devolve
 * no header de resposta — necessário pra rastrear uma requisição através do
 * Gateway (Fase 3) e dos logs estruturados (JSON) que o Datadog vai
 * consumir (RF: "logs estruturados, incluindo correlação entre
 * requisições", evolucao_fase3).
 *
 * Reaproveita o id gerado pelo Gateway (header `X-Request-Id`) quando
 * presente, em vez de sempre gerar um novo — assim uma requisição mantém o
 * mesmo id do Gateway até a aplicação, não só dentro da aplicação.
 *
 * Uso: registrado globalmente em bootstrap/app.php (roda antes de qualquer
 * outro middleware que possa logar algo).
 */
class AssignCorrelationId
{
    public const HEADER = 'X-Request-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = $request->header(self::HEADER) ?: (string) Str::uuid();

        $request->attributes->set('correlation_id', $correlationId);
        Log::withContext(['request_id' => $correlationId]);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set(self::HEADER, $correlationId);

        return $response;
    }
}
