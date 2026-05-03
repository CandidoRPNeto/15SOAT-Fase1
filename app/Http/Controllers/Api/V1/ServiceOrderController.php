<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\MessagingServiceInterface;
use App\Contracts\PaymentServiceInterface;
use App\Enums\ServiceOrderStatus;
use App\Http\Requests\AddPartToOrderRequest;
use App\Http\Requests\AddServiceToOrderRequest;
use App\Http\Requests\StoreServiceOrderRequest;
use App\Http\Resources\ServiceOrderResource;
use App\Models\Part;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderPart;
use App\Models\ServiceOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'ServiceOrders', description: 'Ciclo de vida das Ordens de Serviço')]
class ServiceOrderController extends Controller
{
    public function __construct(
        private readonly MessagingServiceInterface $messaging,
        private readonly PaymentServiceInterface $payment,
    ) {}

    #[OA\Get(
        path: '/api/v1/service-orders',
        summary: 'Lista ordens de serviço',
        security: [['sanctum' => []]],
        tags: ['ServiceOrders'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'client_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Lista de OS')]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $query = ServiceOrder::with(['client', 'vehicle', 'services', 'parts']);

        if ($user->isClient()) {
            $query->where('client_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('client_id') && ! $user->isClient()) {
            $query->where('client_id', $request->client_id);
        }

        return ServiceOrderResource::collection($query->latest()->paginate(15));
    }

    #[OA\Post(
        path: '/api/v1/service-orders',
        summary: 'Cria uma Ordem de Serviço',
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
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'OS criada'),
            new OA\Response(response: 422, description: 'Dados inválidos'),
        ]
    )]
    public function store(StoreServiceOrderRequest $request): JsonResponse
    {
        $order = ServiceOrder::create([
            ...$request->validated(),
            'status' => ServiceOrderStatus::RECEIVED,
        ]);
        $order->load(['client', 'vehicle']);

        $this->messaging->notifyOrderCreated($order);

        return response()->json(new ServiceOrderResource($order), 201);
    }

    #[OA\Get(
        path: '/api/v1/service-orders/{id}',
        summary: 'Exibe OS com serviços e peças',
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
        $order = ServiceOrder::with(['client', 'vehicle', 'services', 'parts'])->findOrFail($id);

        if ($request->user()->isClient() && $order->client_id !== $request->user()->id) {
            return response()->json(['message' => 'Acesso não autorizado.'], 403);
        }

        return response()->json(new ServiceOrderResource($order));
    }

    #[OA\Post(
        path: '/api/v1/service-orders/{id}/services',
        summary: 'Adiciona serviço à OS (mecânico)',
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
            new OA\Response(response: 201, description: 'Serviço adicionado'),
            new OA\Response(response: 422, description: 'OS não permite alteração neste status'),
        ]
    )]
    public function addService(AddServiceToOrderRequest $request, int $id): JsonResponse
    {
        $order = ServiceOrder::findOrFail($id);

        if (! in_array($order->status, [ServiceOrderStatus::RECEIVED, ServiceOrderStatus::IN_DIAGNOSIS])) {
            return response()->json(['message' => 'Serviços só podem ser adicionados nos status Recebida ou Em diagnóstico.'], 422);
        }

        $service = Service::findOrFail($request->service_id);
        $quantity = $request->input('quantity', 1);

        ServiceOrderService::create([
            'service_order_id' => $order->id,
            'service_id' => $service->id,
            'quantity' => $quantity,
            'unit_price' => $service->price,
        ]);

        return response()->json(
            new ServiceOrderResource($order->load(['client', 'vehicle', 'services', 'parts'])),
            201
        );
    }

    #[OA\Post(
        path: '/api/v1/service-orders/{id}/parts',
        summary: 'Adiciona peça à OS (mecânico)',
        security: [['sanctum' => []]],
        tags: ['ServiceOrders'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['part_id'],
                properties: [
                    new OA\Property(property: 'part_id', type: 'integer', example: 1),
                    new OA\Property(property: 'quantity', type: 'integer', example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Peça adicionada'),
            new OA\Response(response: 422, description: 'Estoque insuficiente ou status inválido'),
        ]
    )]
    public function addPart(AddPartToOrderRequest $request, int $id): JsonResponse
    {
        $order = ServiceOrder::findOrFail($id);

        if (! in_array($order->status, [ServiceOrderStatus::RECEIVED, ServiceOrderStatus::IN_DIAGNOSIS])) {
            return response()->json(['message' => 'Peças só podem ser adicionadas nos status Recebida ou Em diagnóstico.'], 422);
        }

        $part = Part::findOrFail($request->part_id);
        $quantity = $request->input('quantity', 1);

        if (! $part->hasStock($quantity)) {
            return response()->json(['message' => "Estoque insuficiente. Disponível: {$part->stock_quantity}."], 422);
        }

        ServiceOrderPart::create([
            'service_order_id' => $order->id,
            'part_id' => $part->id,
            'quantity' => $quantity,
            'unit_price' => $part->price,
        ]);

        $part->decrement('stock_quantity', $quantity);

        return response()->json(
            new ServiceOrderResource($order->load(['client', 'vehicle', 'services', 'parts'])),
            201
        );
    }

    #[OA\Post(
        path: '/api/v1/service-orders/{id}/generate-budget',
        summary: 'Gera orçamento e avança para Aguardando aprovação (mecânico)',
        security: [['sanctum' => []]],
        tags: ['ServiceOrders'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Orçamento gerado e enviado ao cliente'),
            new OA\Response(response: 422, description: 'Status inválido para gerar orçamento'),
        ]
    )]
    public function generateBudget(int $id): JsonResponse
    {
        $order = ServiceOrder::with(['client', 'vehicle', 'orderServices', 'orderParts'])->findOrFail($id);

        if ($order->status !== ServiceOrderStatus::IN_DIAGNOSIS) {
            return response()->json(['message' => 'A OS deve estar Em diagnóstico para gerar o orçamento.'], 422);
        }

        $total = $order->calculateTotal();

        $order->update([
            'status' => ServiceOrderStatus::AWAITING_APPROVAL,
            'total_amount' => $total,
            'budget_sent_at' => now(),
        ]);

        $order->load(['client', 'vehicle', 'services', 'parts']);
        $this->messaging->notifyBudgetReady($order);

        return response()->json(new ServiceOrderResource($order));
    }

    #[OA\Post(
        path: '/api/v1/service-orders/{id}/approve',
        summary: 'Cliente aprova o orçamento',
        security: [['sanctum' => []]],
        tags: ['ServiceOrders'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OS aprovada'),
            new OA\Response(response: 422, description: 'Status inválido para aprovação'),
        ]
    )]
    public function approve(Request $request, int $id): JsonResponse
    {
        $order = ServiceOrder::with(['client', 'vehicle'])->findOrFail($id);

        if ($request->user()->isClient() && $order->client_id !== $request->user()->id) {
            return response()->json(['message' => 'Acesso não autorizado.'], 403);
        }

        if ($order->status !== ServiceOrderStatus::AWAITING_APPROVAL) {
            return response()->json(['message' => 'A OS deve estar Aguardando aprovação para ser aprovada.'], 422);
        }

        $order->update(['status' => ServiceOrderStatus::APPROVED]);

        return response()->json(new ServiceOrderResource($order->load(['services', 'parts'])));
    }

    #[OA\Post(
        path: '/api/v1/service-orders/{id}/cancel',
        summary: 'Cliente cancela o orçamento',
        security: [['sanctum' => []]],
        tags: ['ServiceOrders'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OS cancelada'),
            new OA\Response(response: 422, description: 'Status inválido para cancelamento'),
        ]
    )]
    public function cancel(Request $request, int $id): JsonResponse
    {
        $order = ServiceOrder::with(['client', 'vehicle', 'orderParts'])->findOrFail($id);

        if ($request->user()->isClient() && $order->client_id !== $request->user()->id) {
            return response()->json(['message' => 'Acesso não autorizado.'], 403);
        }

        if ($order->status !== ServiceOrderStatus::AWAITING_APPROVAL) {
            return response()->json(['message' => 'A OS deve estar Aguardando aprovação para ser cancelada.'], 422);
        }

        foreach ($order->orderParts as $orderPart) {
            $orderPart->part->increment('stock_quantity', $orderPart->quantity);
        }

        $order->update(['status' => ServiceOrderStatus::CANCELLED]);

        return response()->json(new ServiceOrderResource($order->load(['services', 'parts'])));
    }

    #[OA\Post(
        path: '/api/v1/service-orders/{id}/start-execution',
        summary: 'Inicia a execução (mecânico)',
        security: [['sanctum' => []]],
        tags: ['ServiceOrders'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OS em execução'),
            new OA\Response(response: 422, description: 'Status inválido'),
        ]
    )]
    public function startExecution(int $id): JsonResponse
    {
        $order = ServiceOrder::findOrFail($id);

        if ($order->status !== ServiceOrderStatus::APPROVED) {
            return response()->json(['message' => 'A OS deve estar Aprovada para iniciar a execução.'], 422);
        }

        $order->update(['status' => ServiceOrderStatus::IN_EXECUTION]);

        return response()->json(new ServiceOrderResource($order->load(['client', 'vehicle', 'services', 'parts'])));
    }

    #[OA\Post(
        path: '/api/v1/service-orders/{id}/finalize',
        summary: 'Finaliza execução (mecânico)',
        security: [['sanctum' => []]],
        tags: ['ServiceOrders'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OS finalizada — cliente notificado para retirar'),
            new OA\Response(response: 422, description: 'Status inválido'),
        ]
    )]
    public function finalize(int $id): JsonResponse
    {
        $order = ServiceOrder::with(['client', 'vehicle'])->findOrFail($id);

        if ($order->status !== ServiceOrderStatus::IN_EXECUTION) {
            return response()->json(['message' => 'A OS deve estar Em execução para ser finalizada.'], 422);
        }

        $order->update([
            'status' => ServiceOrderStatus::FINALIZED,
            'finalized_at' => now(),
        ]);

        $this->messaging->notifyPickupReady($order);

        return response()->json(new ServiceOrderResource($order->load(['services', 'parts'])));
    }

    #[OA\Post(
        path: '/api/v1/service-orders/{id}/pay',
        summary: 'Cliente paga a OS',
        security: [['sanctum' => []]],
        tags: ['ServiceOrders'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pagamento processado'),
            new OA\Response(response: 422, description: 'OS não está Finalizada ou já foi paga'),
        ]
    )]
    public function pay(Request $request, int $id): JsonResponse
    {
        $order = ServiceOrder::with(['client', 'vehicle'])->findOrFail($id);

        if ($request->user()->isClient() && $order->client_id !== $request->user()->id) {
            return response()->json(['message' => 'Acesso não autorizado.'], 403);
        }

        if ($order->status !== ServiceOrderStatus::FINALIZED) {
            return response()->json(['message' => 'A OS deve estar Finalizada para efetuar o pagamento.'], 422);
        }

        if ($order->isPaid()) {
            return response()->json(['message' => 'Esta OS já foi paga.'], 422);
        }

        $result = $this->payment->processPayment($order);

        if (! $result['success']) {
            return response()->json(['message' => 'Falha no processamento do pagamento.'], 422);
        }

        $order->update(['paid_at' => now()]);

        return response()->json([
            'message' => $result['message'],
            'transaction_id' => $result['transaction_id'],
            'order' => new ServiceOrderResource($order->load(['services', 'parts'])),
        ]);
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

        $order->update([
            'status' => ServiceOrderStatus::DELIVERED,
            'delivered_at' => now(),
        ]);

        return response()->json(new ServiceOrderResource($order->load(['services', 'parts'])));
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
