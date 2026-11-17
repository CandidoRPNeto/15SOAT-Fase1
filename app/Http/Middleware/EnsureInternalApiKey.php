<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege endpoints internos (fora de /api/v1, sem Sanctum) que só a
 * Function Serverless de auth por CPF deve chamar — hoje só
 * POST /internal/clients/cpf-lookup. Ver
 * docs/architecture/rfcs/rfc-003-cpf-auth-strategy.md.
 *
 * hash_equals() evita timing attack na comparação da chave — diferença de
 * timing entre "==" numa string errada de tamanho diferente vs. igual
 * pode vazar informação sobre a chave certa, ainda que o risco prático
 * aqui seja baixo (rede interna, não a superfície pública da API).
 */
class EnsureInternalApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.lambda_auth.internal_api_key');
        $provided = (string) $request->header('X-Internal-Api-Key');

        if (! $expected || ! hash_equals((string) $expected, $provided)) {
            return response()->json(['message' => 'Não autorizado.'], 401);
        }

        return $next($request);
    }
}
