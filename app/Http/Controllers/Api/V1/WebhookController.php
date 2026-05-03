<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ServiceOrderStatus;
use App\Models\ServiceOrder;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Webhook', description: 'Webhook para sistema de mensageria externo')]
class WebhookController extends Controller
{
    #[OA\Post(
        path: '/webhook/messaging',
        summary: 'Webhook do sistema de mensageria — retorna OS abertas',
        description: 'Chamado pelo sistema de mensageria externo. Retorna lista de OS com status aberto (não canceladas e não entregues), com status, orçamento, modelo e placa do veículo.',
        tags: ['Webhook'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista de OS abertas',
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
        ]
    )]
    public function messaging(): JsonResponse
    {
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
}
