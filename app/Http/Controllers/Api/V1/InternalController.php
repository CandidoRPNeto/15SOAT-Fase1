<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Endpoints de uso exclusivo de sistemas internos da Fase 3 (hoje só a
 * Function Serverless de auth por CPF) — nunca do frontend/cliente final.
 * Protegido por EnsureInternalApiKey, não por Sanctum. Ver
 * docs/architecture/rfcs/rfc-003-cpf-auth-strategy.md.
 */
#[OA\Tag(name: 'Internal', description: 'Uso exclusivo de sistemas internos (Function Serverless de auth) — Fase 3')]
class InternalController extends Controller
{
    #[OA\Post(
        path: '/internal/clients/cpf-lookup',
        summary: 'Consulta existência e status de um cliente por CPF',
        security: [['internalApiKey' => []]],
        tags: ['Internal'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['cpf_cnpj'],
                properties: [
                    new OA\Property(property: 'cpf_cnpj', type: 'string', example: '123.456.789-00'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Cliente encontrado — retorna user_id, name e status'),
            new OA\Response(response: 401, description: 'X-Internal-Api-Key ausente ou inválida'),
            new OA\Response(response: 404, description: 'Nenhum cliente com esse CPF'),
        ]
    )]
    public function cpfLookup(Request $request): JsonResponse
    {
        $request->validate([
            'cpf_cnpj' => ['required', 'string'],
        ]);

        // Compara só dígitos: a fonte do CPF (Lambda) pode mandar com ou
        // sem pontuação, e a coluna cpf_cnpj guarda formatado
        // (###.###.###-##, ver UserFactory). REPLACE aninhado funciona
        // igual em SQLite (testes) e PostgreSQL (produção) — nem todo
        // banco tem regexp_replace, mas REPLACE de 3 argumentos os dois têm.
        $digits = preg_replace('/\D/', '', $request->string('cpf_cnpj')->toString());

        $user = User::where('role', UserRole::CLIENT)
            ->whereRaw(
                "REPLACE(REPLACE(REPLACE(cpf_cnpj, '.', ''), '-', ''), '/', '') = ?",
                [$digits]
            )
            ->first();

        if (! $user) {
            return response()->json(['exists' => false], 404);
        }

        return response()->json([
            'exists' => true,
            'user_id' => $user->id,
            'name' => $user->name,
            'status' => $user->status->value,
        ]);
    }
}
