<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Items', description: 'CRUD de itens (insumos e peças) com controle de estoque')]
class ItemController extends Controller
{
    #[OA\Get(
        path: '/api/v1/items',
        summary: 'Lista itens',
        security: [['sanctum' => []]],
        tags: ['Items'],
        parameters: [
            new OA\Parameter(name: 'active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'type', in: 'query', required: false, description: 'insumo ou peca', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'low_stock', in: 'query', required: false, description: 'Filtra estoque baixo (<=5)', schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [new OA\Response(response: 200, description: 'Lista de itens')]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Item::query();

        if ($request->has('active')) {
            $query->where('active', filter_var($request->active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->boolean('low_stock')) {
            $query->where('stock_quantity', '<=', 5);
        }

        return ItemResource::collection($query->paginate(15));
    }

    #[OA\Post(
        path: '/api/v1/items',
        summary: 'Cadastra item',
        security: [['sanctum' => []]],
        tags: ['Items'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'price', 'type'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Filtro de óleo'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'part_number', type: 'string', example: 'FO-001'),
                    new OA\Property(property: 'price', type: 'number', format: 'float', example: 35.00),
                    new OA\Property(property: 'stock_quantity', type: 'integer', example: 50),
                    new OA\Property(property: 'active', type: 'boolean', example: true),
                    new OA\Property(property: 'type', type: 'string', enum: ['insumo', 'peca'], example: 'peca'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Item criado'),
            new OA\Response(response: 422, description: 'Dados inválidos'),
        ]
    )]
    public function store(StoreItemRequest $request): JsonResponse
    {
        $item = Item::create($request->validated());

        return response()->json(new ItemResource($item), 201);
    }

    #[OA\Get(
        path: '/api/v1/items/{id}',
        summary: 'Exibe item',
        security: [['sanctum' => []]],
        tags: ['Items'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Dados do item'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        return response()->json(new ItemResource(Item::findOrFail($id)));
    }

    #[OA\Put(
        path: '/api/v1/items/{id}',
        summary: 'Atualiza item',
        security: [['sanctum' => []]],
        tags: ['Items'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'price', type: 'number'),
                    new OA\Property(property: 'stock_quantity', type: 'integer'),
                    new OA\Property(property: 'active', type: 'boolean'),
                    new OA\Property(property: 'type', type: 'string', enum: ['insumo', 'peca']),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Item atualizado'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ]
    )]
    public function update(UpdateItemRequest $request, int $id): JsonResponse
    {
        $item = Item::findOrFail($id);
        $item->update($request->validated());

        return response()->json(new ItemResource($item));
    }

    #[OA\Delete(
        path: '/api/v1/items/{id}',
        summary: 'Remove item',
        security: [['sanctum' => []]],
        tags: ['Items'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Item removido'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        Item::findOrFail($id)->delete();

        return response()->json(null, 204);
    }
}
