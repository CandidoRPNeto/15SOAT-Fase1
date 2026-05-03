<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Models\ServiceItem;
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
        $query = Service::with('items');

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
                    new OA\Property(
                        property: 'items',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'item_id', type: 'integer', example: 1),
                                new OA\Property(property: 'quantity', type: 'integer', example: 1),
                            ]
                        )
                    ),
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
        $service = Service::create($request->safe()->except('items'));

        if ($request->has('items')) {
            foreach ($request->items as $item) {
                ServiceItem::create([
                    'service_id' => $service->id,
                    'item_id' => $item['item_id'],
                    'quantity' => $item['quantity'] ?? 1,
                ]);
            }
        }

        return response()->json(new ServiceResource($service->load('items')), 201);
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
        return response()->json(new ServiceResource(Service::with('items')->findOrFail($id)));
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
                    new OA\Property(
                        property: 'items',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'item_id', type: 'integer'),
                                new OA\Property(property: 'quantity', type: 'integer'),
                            ]
                        )
                    ),
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
        $service->update($request->safe()->except('items'));

        if ($request->has('items')) {
            ServiceItem::where('service_id', $service->id)->delete();
            foreach ($request->items as $item) {
                ServiceItem::create([
                    'service_id' => $service->id,
                    'item_id' => $item['item_id'],
                    'quantity' => $item['quantity'] ?? 1,
                ]);
            }
        }

        return response()->json(new ServiceResource($service->load('items')));
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
