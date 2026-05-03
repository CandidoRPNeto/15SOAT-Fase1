<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Vehicles', description: 'CRUD de veículos')]
class VehicleController extends Controller
{
    #[OA\Get(
        path: '/api/v1/vehicles',
        summary: 'Lista veículos',
        security: [['sanctum' => []]],
        tags: ['Vehicles'],
        parameters: [
            new OA\Parameter(name: 'client_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'plate', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [new OA\Response(response: 200, description: 'Lista de veículos')]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Vehicle::with('client');

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('plate')) {
            $query->whereRaw('LOWER(plate) LIKE ?', ['%' . strtolower($request->plate) . '%']);
        }

        return VehicleResource::collection($query->paginate(15));
    }

    #[OA\Post(
        path: '/api/v1/vehicles',
        summary: 'Cadastra veículo',
        security: [['sanctum' => []]],
        tags: ['Vehicles'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['client_id', 'plate', 'brand', 'model', 'year'],
                properties: [
                    new OA\Property(property: 'client_id', type: 'integer', example: 3),
                    new OA\Property(property: 'plate', type: 'string', example: 'ABC-1234'),
                    new OA\Property(property: 'brand', type: 'string', example: 'Toyota'),
                    new OA\Property(property: 'model', type: 'string', example: 'Corolla'),
                    new OA\Property(property: 'year', type: 'integer', example: 2022),
                    new OA\Property(property: 'color', type: 'string', example: 'Prata'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Veículo criado'),
            new OA\Response(response: 422, description: 'Dados inválidos'),
        ]
    )]
    public function store(StoreVehicleRequest $request): JsonResponse
    {
        $vehicle = Vehicle::create($request->validated());

        return response()->json(new VehicleResource($vehicle->load('client')), 201);
    }

    #[OA\Get(
        path: '/api/v1/vehicles/{id}',
        summary: 'Exibe veículo',
        security: [['sanctum' => []]],
        tags: ['Vehicles'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Dados do veículo'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $vehicle = Vehicle::with('client')->findOrFail($id);

        return response()->json(new VehicleResource($vehicle));
    }

    #[OA\Put(
        path: '/api/v1/vehicles/{id}',
        summary: 'Atualiza veículo',
        security: [['sanctum' => []]],
        tags: ['Vehicles'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'plate', type: 'string'),
                    new OA\Property(property: 'brand', type: 'string'),
                    new OA\Property(property: 'model', type: 'string'),
                    new OA\Property(property: 'year', type: 'integer'),
                    new OA\Property(property: 'color', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Veículo atualizado'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ]
    )]
    public function update(UpdateVehicleRequest $request, int $id): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($id);
        $vehicle->update($request->validated());

        return response()->json(new VehicleResource($vehicle->load('client')));
    }

    #[OA\Delete(
        path: '/api/v1/vehicles/{id}',
        summary: 'Remove veículo',
        security: [['sanctum' => []]],
        tags: ['Vehicles'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Veículo removido'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($id);
        $vehicle->delete();

        return response()->json(null, 204);
    }
}
