<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\SagaInstance;
use App\Models\SagaStep;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderStatusHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ServiceOrderHistoryController extends Controller
{
    #[OA\Get(
        path: '/api/v1/service-orders/{id}/history',
        summary: 'Histórico de status da OS e passos da saga',
        security: [['sanctum' => []]],
        tags: ['ServiceOrders'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'status_history (cronológico) e saga (status + passos)'),
            new OA\Response(response: 403, description: 'Acesso não autorizado'),
            new OA\Response(response: 404, description: 'OS não encontrada'),
        ]
    )]
    public function show(Request $request, int $id): JsonResponse
    {
        $order = ServiceOrder::findOrFail($id);

        if ($request->user()->isClient() && $order->client_id !== $request->user()->id) {
            return response()->json(['message' => 'Acesso não autorizado.'], 403);
        }

        return response()->json([
            'service_order_id' => $order->id,
            'number' => $order->number,
            'status' => $order->status->value,
            'status_history' => $this->statusHistory($order),
            'saga' => $this->saga($order),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function statusHistory(ServiceOrder $order): array
    {
        return ServiceOrderStatusHistory::where('service_order_id', $order->id)->orderBy('id')->get()
            ->map(fn (ServiceOrderStatusHistory $entry) => [
                'from' => $entry->from_status,
                'to' => $entry->to_status,
                'reason' => $entry->reason,
                'at' => $entry->created_at?->toIso8601String(),
            ])->all();
    }

    /** @return array<string, mixed>|null */
    private function saga(ServiceOrder $order): ?array
    {
        $saga = SagaInstance::with('steps')->where('service_order_id', $order->id)->first();

        if ($saga === null) {
            return null;
        }

        return [
            'id' => $saga->id,
            'status' => $saga->status->value,
            'failure_reason' => $saga->failure_reason,
            'steps' => $saga->steps->map(fn (SagaStep $step) => [
                'name' => $step->name->value,
                'status' => $step->status->value,
                'attempts' => $step->attempts,
                'detail' => $step->detail,
                'updated_at' => $step->updated_at?->toIso8601String(),
            ])->all(),
        ];
    }
}
