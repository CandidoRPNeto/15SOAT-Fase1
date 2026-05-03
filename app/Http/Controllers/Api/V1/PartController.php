<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StorePartRequest;
use App\Http\Requests\UpdatePartRequest;
use App\Http\Resources\PartResource;
use App\Models\Part;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Parts', description: 'CRUD de peças e insumos com controle de estoque')]
class PartController extends Controller
{
    #[OA\Get(
        path: '/api/v1/parts',
        summary: 'Lista peças',
        security: [['sanctum' => []]],
        tags: ['Parts'],
        parameters: [
            new OA\Parameter(name: 'active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'low_stock', in: 'query', required: false, description: 'Filtra estoque baixo (<=5)', schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [new OA\Response(response: 200, description: 'Lista de peças')]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Part::query();

        if ($request->has('active')) {
            $query->where('active', filter_var($request->active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->boolean('low_stock')) {
            $query->where('stock_quantity', '<=', 5);
        }

        return PartResource::collection($query->paginate(15));
    }

    #[OA\Post(
        path: '/api/v1/parts',
        summary: 'Cadastra peça',
        security: [['sanctum' => []]],
        tags: ['Parts'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'price'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Filtro de óleo'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'part_number', type: 'string', example: 'FO-001'),
                    new OA\Property(property: 'price', type: 'number', format: 'float', example: 35.00),
                    new OA\Property(property: 'stock_quantity', type: 'integer', example: 50),
                    new OA\Property(property: 'active', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Peça criada'),
            new OA\Response(response: 422, description: 'Dados inválidos'),
        ]
    )]
    public function store(StorePartRequest $request): JsonResponse
    {
        $part = Part::create($request->validated());

        return response()->json(new PartResource($part), 201);
    }

    #[OA\Get(
        path: '/api/v1/parts/{id}',
        summary: 'Exibe peça',
        security: [['sanctum' => []]],
        tags: ['Parts'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Dados da peça'),
            new OA\Response(response: 404, description: 'Não encontrada'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        return response()->json(new PartResource(Part::findOrFail($id)));
    }

    #[OA\Put(
        path: '/api/v1/parts/{id}',
        summary: 'Atualiza peça',
        security: [['sanctum' => []]],
        tags: ['Parts'],
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
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Peça atualizada'),
            new OA\Response(response: 404, description: 'Não encontrada'),
        ]
    )]
    public function update(UpdatePartRequest $request, int $id): JsonResponse
    {
        $part = Part::findOrFail($id);
        $part->update($request->validated());

        return response()->json(new PartResource($part));
    }

    #[OA\Delete(
        path: '/api/v1/parts/{id}',
        summary: 'Remove peça',
        security: [['sanctum' => []]],
        tags: ['Parts'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Peça removida'),
            new OA\Response(response: 404, description: 'Não encontrada'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        Part::findOrFail($id)->delete();

        return response()->json(null, 204);
    }
}
