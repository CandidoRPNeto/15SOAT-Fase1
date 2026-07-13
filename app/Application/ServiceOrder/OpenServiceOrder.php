<?php

namespace App\Application\ServiceOrder;

use App\Application\Ports\ServiceOrderRepository;
use App\Contracts\MessagingServiceInterface;
use App\Domain\ServiceOrder\ServiceOrderStatus;
use App\Models\Item;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use App\Models\ServiceOrderService;

class OpenServiceOrder
{
    public function __construct(
        private readonly ServiceOrderRepository $repository,
        private readonly MessagingServiceInterface $messaging,
    ) {}

    public function execute(array $data): ServiceOrder
    {
        $order = $this->repository->create([
            'client_id' => $data['client_id'],
            'vehicle_id' => $data['vehicle_id'],
            'notes' => $data['notes'] ?? null,
            'status' => ServiceOrderStatus::RECEIVED,
        ]);

        foreach ($data['services'] ?? [] as $entry) {
            $this->attachService($order, (int) $entry['service_id'], (int) ($entry['quantity'] ?? 1));
        }

        foreach ($data['items'] ?? [] as $entry) {
            $this->attachItem($order, (int) $entry['item_id'], (int) ($entry['quantity'] ?? 1));
        }

        $order->load(['client', 'vehicle']);
        $this->messaging->notifyOrderCreated($order);

        return $order;
    }

    private function attachService(ServiceOrder $order, int $serviceId, int $quantity): void
    {
        $service = Service::with('serviceItems.item')->findOrFail($serviceId);

        ServiceOrderService::create([
            'service_order_id' => $order->id,
            'service_id' => $service->id,
            'quantity' => $quantity,
            'unit_price' => $service->price,
        ]);

        foreach ($service->serviceItems as $serviceItem) {
            $this->attachItem($order, $serviceItem->item_id, $serviceItem->quantity, (float) $serviceItem->item->price);
        }
    }

    private function attachItem(ServiceOrder $order, int $itemId, int $quantity, ?float $unitPrice = null): void
    {
        $existing = ServiceOrderItem::where('service_order_id', $order->id)
            ->where('item_id', $itemId)
            ->first();

        if ($existing) {
            $existing->increment('quantity', $quantity);

            return;
        }

        ServiceOrderItem::create([
            'service_order_id' => $order->id,
            'item_id' => $itemId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice ?? Item::findOrFail($itemId)->price,
        ]);
    }
}
