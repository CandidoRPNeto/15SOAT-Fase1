<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fase 3 (evolucao_fase3): "logs estruturados... incluindo... latência das
 * APIs". Mede o tempo da requisição e adiciona `duration_ms` ao contexto
 * de log (junto com o `request_id` de AssignCorrelationId) — vira campo
 * consultável no canal `json` (config/logging.php), consumido pelo
 * Datadog Agent (Epic 6, ver ADR-009).
 *
 * Não emite métrica Datadog diretamente — isso exigiria um
 * `datadog_logs_metric` extraindo `duration_ms` dos logs, deixado como
 * próximo passo em ADR-009 (não fabricado aqui sem poder validar o schema
 * desse recurso Terraform).
 */
class LogRequestLatency
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        $durationMs = (int) round((microtime(true) - $start) * 1000);
        Log::withContext(['duration_ms' => $durationMs]);
        $response->headers->set('X-Response-Time-Ms', (string) $durationMs);

        return $response;
    }
}
