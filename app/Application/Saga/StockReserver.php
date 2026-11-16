<?php

namespace App\Application\Saga;

use App\Models\Item;
use App\Models\ServiceOrder;
use App\Models\StockReservation;
use Illuminate\Support\Collection;

/**
 * Local, compensable saga step: reserves the order's items from stock.
 * All-or-nothing: if any item lacks stock nothing is reserved.
 *
 * Example: $shortages = $reserver->reserve($order); // [] on success
 */
class StockReserver
{
    /** @return list<string> human-readable shortages; empty when reserved */
    public function reserve(ServiceOrder $order): array
    {
        $order->loadMissing('orderItems');
        $items = Item::whereIn('id', $order->orderItems->pluck('item_id'))->lockForUpdate()->get()->keyBy('id');
        $shortages = $this->shortages($order, $items);

        if ($shortages !== []) {
            return $shortages;
        }

        foreach ($order->orderItems as $line) {
            $items[$line->item_id]->decrement('stock_quantity', $line->quantity);
            StockReservation::create(['service_order_id' => $order->id, 'item_id' => $line->item_id, 'quantity' => $line->quantity]);
        }

        return [];
    }

    /** Returns reserved quantities to stock; idempotent. */
    public function release(ServiceOrder $order): int
    {
        $open = StockReservation::where('service_order_id', $order->id)->whereNull('released_at')->get();

        foreach ($open as $reservation) {
            Item::whereKey($reservation->item_id)->increment('stock_quantity', $reservation->quantity);
            $reservation->update(['released_at' => now()]);
        }

        return $open->count();
    }

    /**
     * @param  Collection<int, Item>  $items
     * @return list<string>
     */
    private function shortages(ServiceOrder $order, $items): array
    {
        $shortages = [];

        foreach ($order->orderItems as $line) {
            $available = (int) $items[$line->item_id]->stock_quantity;
            if ($line->quantity > $available) {
                $shortages[] = "{$items[$line->item_id]->name}: requested {$line->quantity}, available {$available}";
            }
        }

        return $shortages;
    }
}
