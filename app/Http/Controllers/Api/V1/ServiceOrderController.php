<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Saga\CompleteServiceOrderSaga;
use App\Application\Saga\ServiceOrderStatusTransition;
use App\Application\ServiceOrder\GetServiceOrderStatus;
use App\Application\ServiceOrder\ListServiceOrders;
use App\Application\ServiceOrder\OpenServiceOrder;
use App\Domain\ServiceOrder\ServiceOrderPolicy;
use App\Domain\ServiceOrder\ServiceOrderStatus;
use App\Http\Requests\AddItemToOrderRequest;
use App\Http\Requests\AddServiceToOrderRequest;
use App\Http\Requests\StoreServiceOrderRequest;
use App\Http\Resources\ServiceOrderResource;
use App\Models\Item;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use App\Models\ServiceOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'ServiceOrders', description: 'Ciclo de vida das Ordens de Serviço')]
class ServiceOrderController extends Controller
{
    public function __construct(
        private readonly OpenServiceOrder $openServiceOrder,
        private readonly GetServiceOrderStatus $getServiceOrderStatus,
        private readonly ServiceOrderStatusTransition $statusTransition,
        private readonly CompleteServiceOrderSaga $completeSaga,
    ) {}

    #[OA\Get(
        path: '/api/v1/service-orders',
        summary: 'Lista ordens de serviço',
        security: [['sanctum' => []]],
        tags: ['ServiceOrders'],
        description: 'Sem o filtro `status`, exclui OS finalizadas/entregues e ordena por prioridade '
            .'(Em Execução > Aguardando Aprovação > Diagnóstico > Recebida, mais antigas primeiro).',
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'client_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Lista de OS')]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $query = ServiceOrder::with(['client', 'vehicle', 'services', 'orderItems.item']);

        if ($user->isClient()) {
            $query->where('client_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->whereNotIn('status', ListServiceOrders::excludedStatuses());
        }

        if ($request->filled('client_id') && ! $user->isClient()) {
            $query->where('client_id', $request->client_id);
        }

        $orders = ListServiceOrders::sort($query->get());

        $perPage = 15;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginated = new LengthAwarePaginator(
            $orders->forPage($page, $perPage)->values(),
            $orders->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => $request->query()],
        );

        return ServiceOrderResource::collection($paginated);
    }

    #[OA\Post(
        path: '/api/v1/service-orders',
        summary: 'Cria uma Ordem de Serviço e inicia a saga (enfileira diagnóstico no Execution Service)',
        security: [['sanctum' => []]],
        tags: ['ServiceOrders'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['client_id', 'vehicle_id'],
                properties: [
                    new OA\Property(property: 'client_id', type: 'integer', example: 3),
                    new OA\Property(property: 'vehicle_id', type: 'integer', example: 1),
                    new OA\Property(property: 'notes', type: 'string'),
                    new OA\Property(
                        property: 'services',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'service_id', type: 'integer', example: 1),
                                new OA\Property(property: 'quantity', type: 'integer', example: 1),
                            ]
                        )
                    ),
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
            new OA\Response(response: 201, description: 'OS criada, com identificação única e, se informados, serviços/itens já anexados'),
            new OA\Response(response: 422, description: 'Dados inválidos'),
        ]
    )]
    public function store(StoreServiceOrderRequest $request): JsonResponse
    {
        $order = $this->openServiceOrder->execute(
            $request->validated(),
            (string) $request->attributes->get('correlation_id', (string) Str::uuid()),
        );

        return response()->json(
            new ServiceOrderResource($order->load(['services', 'orderItems.item'])),
            201
        );
    }

    #[OA\Get(
        path: '/api/v1/service-orders/{id}',
        summary: 'Exibe OS com serviços e itens',
        security: [['sanctum' => []]],
        tags: ['ServiceOrders'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Dados completos da OS'),
            new OA\Response(response: 404, description: 'OS não encontrada'),
        ]
    )]
    public function show(Request $request, int $id): JsonResponse
    {
        $order = ServiceOrder::with(['client', 'vehicle', 'services', 'orderItems.item'])->findOrFail($id);

        if ($request->user()->isClient() && $order->client_id !== $request->user()->id) {
            return response()->json(['message' => 'Acesso não autorizado.'], 403);
        }

        return response()->json(new ServiceOrderResource($order));
    }

    #[OA\Get(
        path: '/api/v1/service-orders/{id}/status',
        summary: 'Consulta apenas a situação atual da OS',
        security: [['sanctum' => []]],
        tags: ['ServiceOrders'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Situação atual da OS'),
            new OA\Response(response: 403, description: 'Acesso não autorizado'),
            new OA\Response(response: 404, description: 'OS não encontrada'),
        ]
    )]
    public function status(Request $request, int $id): JsonResponse
    {
        $order = $this->getServiceOrderStatus->execute($id);

        if ($request->user()->isClient() && $order->client_id !== $request->user()->id) {
            return response()->json(['message' => 'Acesso não autorizado.'], 403);
        }

        return response()->json([
            'id' => $order->id,
            'number' => $order->number,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
        ]);
    }

    #[OA\Post(
        path: '/api/v1/service-orders/{id}/services',
        summary: 'Adiciona serviço à OS e inclui automaticamente seus itens necessários (mecânico)',
        security: [['sanctum' => []]],
        tags: ['ServiceOrders'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['service_id'],
                properties: [
                    new OA\Property(property: 'service_id', type: 'integer', example: 1),
                    new OA\Property(property: 'quantity', type: 'integer', example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Serviço adicionado e itens criados automaticamente'),
            new OA\Response(response: 422, description: 'OS não permite alteração neste status'),
        ]
    )]
    public function addService(AddServiceToOrderRequest $request, int $id): JsonResponse
    {
        $order = ServiceOrder::findOrFail($id);

        if (! ServiceOrderPolicy::canModifyItems($order->status)) {
            return response()->json(['message' => 'Serviços só podem ser adicionados nos status Recebida ou Em diagnóstico.'], 422);
        }

        $service = Service::with('serviceItems.item')->findOrFail($request->service_id);
        $quantity = $request->input('quantity', 1);

        ServiceOrderService::create([
            'service_order_id' => $order->id,
            'service_id' => $service->id,
            'quantity' => $quantity,
            'unit_price' => $service->price,
        ]);

        foreach ($service->serviceItems as $serviceItem) {
            $existing = ServiceOrderItem::where('service_order_id', $order->id)
                ->where('item_id', $serviceItem->item_id)
                ->first();

            if ($existing) {
                $existing->increment('quantity', $serviceItem->quantity);
            } else {
                ServiceOrderItem::create([
                    'service_order_id' => $order->id,
                    'item_id' => $serviceItem->item_id,
                    'quantity' => $serviceItem->quantity,
                    'unit_price' => $serviceItem->item->price,
                ]);
            }
        }

        return response()->json(
            new ServiceOrderResource($order->load(['client', 'vehicle', 'services', 'orderItems.item'])),
            201
        );
    }

    #[OA\Post(
        path: '/api/v1/service-orders/{id}/items',
        summary: 'Adiciona item manualmente à lista de itens da OS (mecânico)',
        security: [['sanctum' => []]],
        tags: ['ServiceOrders'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['item_id'],
                properties: [
                    new OA\Property(property: 'item_id', type: 'integer', example: 1),
                    new OA\Property(property: 'quantity', type: 'integer', example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Item adicionado'),
            new OA\Response(response: 422, description: 'Status inválido'),
        ]
    )]
    public function addItem(AddItemToOrderRequest $request, int $id): JsonResponse
    {
        $order = ServiceOrder::findOrFail($id);

        if (! ServiceOrderPolicy::canModifyItems($order->status)) {
            return response()->json(['message' => 'Itens só podem ser adicionados nos status Recebida ou Em diagnóstico.'], 422);
        }

        $item = Item::findOrFail($request->item_id);
        $quantity = $request->input('quantity', 1);

        $existing = ServiceOrderItem::where('service_order_id', $order->id)
            ->where('item_id', $item->id)
            ->first();

        if ($existing) {
            $existing->increment('quantity', $quantity);
        } else {
            ServiceOrderItem::create([
                'service_order_id' => $order->id,
                'item_id' => $item->id,
                'quantity' => $quantity,
                'unit_price' => $item->price,
            ]);
        }

        return response()->json(
            new ServiceOrderResource($order->load(['client', 'vehicle', 'services', 'orderItems.item'])),
            201
        );
    }

    #[OA\Delete(
        path: '/api/v1/service-orders/{id}/items/{itemId}',
        summary: 'Remove item da lista de itens da OS (mecânico)',
        security: [['sanctum' => []]],
        tags: ['ServiceOrders'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'itemId', in: 'path', required: true, description: 'ID do registro service_order_items', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Item removido da OS'),
            new OA\Response(response: 404, description: 'Item não encontrado na OS'),
            new OA\Response(response: 422, description: 'Status inválido'),
        ]
    )]
    public function removeItem(int $id, int $itemId): JsonResponse
    {
        $order = ServiceOrder::findOrFail($id);

        if (! ServiceOrderPolicy::canModifyItems($order->status)) {
            return response()->json(['message' => 'Itens só podem ser removidos nos status Recebida ou Em diagnóstico.'], 422);
        }

        $orderItem = ServiceOrderItem::where('service_order_id', $order->id)
            ->where('id', $itemId)
            ->firstOrFail();

        $orderItem->delete();

        return response()->json(
            new ServiceOrderResource($order->load(['client', 'vehicle', 'services', 'orderItems.item']))
        );
    }

    #[OA\Post(
        path: '/api/v1/service-orders/{id}/deliver',
        summary: 'Recepcionista entrega o carro após pagamento',
        security: [['sanctum' => []]],
        tags: ['ServiceOrders'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Veículo entregue — OS encerrada'),
            new OA\Response(response: 422, description: 'OS não paga ou status inválido'),
        ]
    )]
    public function deliver(int $id): JsonResponse
    {
        $order = ServiceOrder::with(['client', 'vehicle'])->findOrFail($id);

        if (! $order->isDeliverable()) {
            return response()->json(['message' => 'A OS deve estar Finalizada e paga para ser entregue.'], 422);
        }

        DB::transaction(function () use ($order) {
            $this->statusTransition->apply($order, ServiceOrderStatus::DELIVERED, 'veículo entregue ao cliente', ['delivered_at' => now()]);
            $this->completeSaga->execute($order);
        });

        return response()->json(new ServiceOrderResource($order->load(['services', 'orderItems.item'])));
    }

    #[OA\Get(
        path: '/api/v1/service-orders/stats',
        summary: 'Monitoramento: tempo médio de execução por serviço',
        security: [['sanctum' => []]],
        tags: ['ServiceOrders'],
        responses: [new OA\Response(response: 200, description: 'Estatísticas de execução')]
    )]
    public function stats(): JsonResponse
    {
        $stats = ServiceOrder::query()
            ->whereNotNull('finalized_at')
            ->whereIn('status', [ServiceOrderStatus::FINALIZED, ServiceOrderStatus::DELIVERED])
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (finalized_at - created_at))/60) as avg_minutes')
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw('MIN(EXTRACT(EPOCH FROM (finalized_at - created_at))/60) as min_minutes')
            ->selectRaw('MAX(EXTRACT(EPOCH FROM (finalized_at - created_at))/60) as max_minutes')
            ->first();

        return response()->json([
            'avg_execution_minutes' => round((float) $stats->avg_minutes, 2),
            'min_execution_minutes' => round((float) $stats->min_minutes, 2),
            'max_execution_minutes' => round((float) $stats->max_minutes, 2),
            'total_orders_computed' => (int) $stats->total_orders,
        ]);
    }
}
