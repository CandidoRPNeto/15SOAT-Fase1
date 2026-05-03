<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Clients', description: 'CRUD de clientes (perfis: receptionist, mechanic)')]
class ClientController extends Controller
{
    #[OA\Get(
        path: '/api/v1/clients',
        summary: 'Lista todos os clientes',
        security: [['sanctum' => []]],
        tags: ['Clients'],
        parameters: [
            new OA\Parameter(name: 'cpf_cnpj', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'name', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lista de clientes'),
            new OA\Response(response: 403, description: 'Acesso não autorizado'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = User::where('role', UserRole::CLIENT);

        if ($request->filled('cpf_cnpj')) {
            $query->where('cpf_cnpj', $request->cpf_cnpj);
        }

        if ($request->filled('name')) {
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($request->name) . '%']);
        }

        return UserResource::collection($query->paginate(15));
    }

    #[OA\Post(
        path: '/api/v1/clients',
        summary: 'Cadastra novo cliente',
        security: [['sanctum' => []]],
        tags: ['Clients'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'cpf_cnpj'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Maria Silva'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'maria@email.com'),
                    new OA\Property(property: 'password', type: 'string', example: 'secret123'),
                    new OA\Property(property: 'cpf_cnpj', type: 'string', example: '123.456.789-00'),
                    new OA\Property(property: 'phone', type: 'string', example: '(11) 98888-1111'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Cliente criado'),
            new OA\Response(response: 422, description: 'Dados inválidos'),
        ]
    )]
    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = User::create([
            ...$request->validated(),
            'role' => UserRole::CLIENT,
        ]);

        return response()->json(new UserResource($client), 201);
    }

    #[OA\Get(
        path: '/api/v1/clients/{id}',
        summary: 'Exibe um cliente',
        security: [['sanctum' => []]],
        tags: ['Clients'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Dados do cliente'),
            new OA\Response(response: 404, description: 'Cliente não encontrado'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $client = User::where('role', UserRole::CLIENT)->findOrFail($id);

        return response()->json(new UserResource($client));
    }

    #[OA\Put(
        path: '/api/v1/clients/{id}',
        summary: 'Atualiza um cliente',
        security: [['sanctum' => []]],
        tags: ['Clients'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'phone', type: 'string'),
                    new OA\Property(property: 'cpf_cnpj', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Cliente atualizado'),
            new OA\Response(response: 404, description: 'Cliente não encontrado'),
        ]
    )]
    public function update(UpdateClientRequest $request, int $id): JsonResponse
    {
        $client = User::where('role', UserRole::CLIENT)->findOrFail($id);
        $client->update($request->validated());

        return response()->json(new UserResource($client));
    }

    #[OA\Delete(
        path: '/api/v1/clients/{id}',
        summary: 'Remove um cliente',
        security: [['sanctum' => []]],
        tags: ['Clients'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Cliente removido'),
            new OA\Response(response: 404, description: 'Cliente não encontrado'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $client = User::where('role', UserRole::CLIENT)->findOrFail($id);
        $client->delete();

        return response()->json(null, 204);
    }
}
