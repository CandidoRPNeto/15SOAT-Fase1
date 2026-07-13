<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\ServiceOrder\ApproveBudget;
use App\Domain\ServiceOrder\Exceptions\InvalidStatusTransitionException;
use App\Domain\ServiceOrder\ServiceOrderStatus;
use App\Http\Resources\ServiceOrderResource;
use App\Models\ServiceOrder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Webhook', description: 'Webhook para sistema de mensageria externo')]
class WebhookController extends Controller
{
    #[OA\Post(
        path: '/webhook/messaging',
        summary: 'Webhook do sistema de mensageria — retorna OS abertas ou processa aprovação de orçamento',
        description: 'Chamado pelo sistema de mensageria externo. Sem corpo, retorna a lista de OS com status '
            .'aberto (não canceladas e não entregues). Com `event` = `budget_approved` ou `budget_rejected` e '
            .'`order_number`, aplica a decisão do cliente sobre o orçamento.',
        tags: ['Webhook'],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'event', type: 'string', enum: ['budget_approved', 'budget_rejected']),
                    new OA\Property(property: 'order_number', type: 'string', example: 'OS-2026-00001'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista de OS abertas ou confirmação da decisão de orçamento',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'open_orders', type: 'array', items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'number', type: 'string'),
                                new OA\Property(property: 'status', type: 'string'),
                                new OA\Property(property: 'status_label', type: 'string'),
                                new OA\Property(property: 'total_amount', type: 'number'),
                                new OA\Property(property: 'vehicle_model', type: 'string'),
                                new OA\Property(property: 'vehicle_plate', type: 'string'),
                            ]
                        )),
                        new OA\Property(property: 'total', type: 'integer'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'OS não encontrada (informada em order_number)'),
            new OA\Response(response: 422, description: 'Evento inválido ou status da OS não permite a decisão'),
        ]
    )]
    public function messaging(Request $request, ApproveBudget $approveBudget): JsonResponse
    {
        if ($request->filled('event')) {
            return $this->processBudgetDecision($request, $approveBudget);
        }

        $openOrders = ServiceOrder::with('vehicle')
            ->whereNotIn('status', [
                ServiceOrderStatus::CANCELLED,
                ServiceOrderStatus::DELIVERED,
            ])
            ->latest()
            ->get()
            ->map(fn (ServiceOrder $order) => [
                'number' => $order->number,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'total_amount' => $order->total_amount,
                'vehicle_model' => "{$order->vehicle->brand} {$order->vehicle->model} {$order->vehicle->year}",
                'vehicle_plate' => $order->vehicle->plate,
            ]);

        return response()->json([
            'open_orders' => $openOrders,
            'total' => $openOrders->count(),
        ]);
    }

    private function processBudgetDecision(Request $request, ApproveBudget $approveBudget): JsonResponse
    {
        $event = $request->string('event')->toString();

        if (! in_array($event, ['budget_approved', 'budget_rejected'], true)) {
            return response()->json(['message' => 'Evento inválido. Use budget_approved ou budget_rejected.'], 422);
        }

        if (! $request->filled('order_number')) {
            return response()->json(['message' => 'O campo order_number é obrigatório.'], 422);
        }

        try {
            $order = $approveBudget->execute(
                $request->string('order_number')->toString(),
                $event === 'budget_approved',
            );
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'OS não encontrada.'], 404);
        } catch (InvalidStatusTransitionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Decisão de orçamento processada com sucesso.',
            'order' => new ServiceOrderResource($order),
        ]);
    }
}
