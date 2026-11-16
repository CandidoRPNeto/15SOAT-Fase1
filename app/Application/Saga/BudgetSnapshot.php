<?php

namespace App\Application\Saga;

use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use App\Models\ServiceOrderService;

/**
 * Builds the cmd.billing.create_budget payload. Billing owns the budget but
 * not the catalog, so prices are frozen here at diagnosis time.
 *
 * Example: $payload = (new BudgetSnapshot)->forOrder($order);
 */
class BudgetSnapshot
{
    /** @return array{service_order_id: int, service_order_number: string, client_id: int, lines: list<array<string, mixed>>, total: float} */
    public function forOrder(ServiceOrder $order): array
    {
        $order->load(['orderServices.service', 'orderItems.item']);

        $lines = [
            ...$order->orderServices->map(fn (ServiceOrderService $line) => $this->line('service', $line->service_id, $line->service->name, $line->quantity, (float) $line->unit_price))->all(),
            ...$order->orderItems->map(fn (ServiceOrderItem $line) => $this->line('item', $line->item_id, $line->item->name, $line->quantity, (float) $line->unit_price))->all(),
        ];

        return [
            'service_order_id' => $order->id,
            'service_order_number' => $order->number,
            'client_id' => $order->client_id,
            'lines' => $lines,
            'total' => $order->calculateTotal(),
        ];
    }

    /** @return array{kind: string, reference_id: int, description: string, quantity: int, unit_price: float} */
    private function line(string $kind, int $referenceId, string $description, int $quantity, float $unitPrice): array
    {
        return [
            'kind' => $kind,
            'reference_id' => $referenceId,
            'description' => $description,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
        ];
    }
}
