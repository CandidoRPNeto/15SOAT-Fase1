<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Services', description: 'CRUD de serviços')]
class ServiceController extends Controller
{
    #[OA\Get(
        path: '/api/v1/services',
        summary: 'Lista serviços',
        security: [['sanctum' => []]],
        tags: ['Services'],
        parameters: [
            new OA\Parameter(name: 'active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [new OA\Response(response: 200, description: 'Lista de serviços')]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Service::query();

        if ($request->has('active')) {
            $query->where('active', filter_var($request->active, FILTER_VALIDATE_BOOLEAN));
        }

        return ServiceResource::collection($query->paginate(15));
    }

    #[OA\Post(
        path: '/api/v1/services',
        summary: 'Cadastra serviço',
        security: [['sanctum' => []]],
        tags: ['Services'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'price'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Troca de óleo'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'price', type: 'number', format: 'float', example: 120.00),
                    new OA\Property(property: 'avg_execution_minutes', type: 'integer', example: 60),
                    new OA\Property(property: 'active', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Serviço criado'),
            new OA\Response(response: 422, description: 'Dados inválidos'),
        ]
    )]
    public function store(StoreServiceRequest $request): JsonResponse
    {
        $service = Service::create($request->validated());

        return response()->json(new ServiceResource($service), 201);
    }

    #[OA\Get(
        path: '/api/v1/services/{id}',
        summary: 'Exibe serviço',
        security: [['sanctum' => []]],
        tags: ['Services'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Dados do serviço'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        return response()->json(new ServiceResource(Service::findOrFail($id)));
    }

    #[OA\Put(
        path: '/api/v1/services/{id}',
        summary: 'Atualiza serviço',
        security: [['sanctum' => []]],
        tags: ['Services'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'price', type: 'number'),
                    new OA\Property(property: 'avg_execution_minutes', type: 'integer'),
                    new OA\Property(property: 'active', type: 'boolean'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Serviço atualizado'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ]
    )]
    public function update(UpdateServiceRequest $request, int $id): JsonResponse
    {
        $service = Service::findOrFail($id);
        $service->update($request->validated());

        return response()->json(new ServiceResource($service));
    }

    #[OA\Delete(
        path: '/api/v1/services/{id}',
        summary: 'Remove serviço',
        security: [['sanctum' => []]],
        tags: ['Services'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Serviço removido'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        Service::findOrFail($id)->delete();

        return response()->json(null, 204);
    }
}
