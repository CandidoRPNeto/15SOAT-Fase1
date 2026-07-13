<?php

namespace App\Application\ServiceOrder;

use App\Domain\ServiceOrder\ServiceOrderStatus;
use App\Models\ServiceOrder;
use Illuminate\Support\Collection;

class ListServiceOrders
{
    private const array PRIORITY = [
        ServiceOrderStatus::IN_EXECUTION->value => 0,
        ServiceOrderStatus::AWAITING_APPROVAL->value => 1,
        ServiceOrderStatus::IN_DIAGNOSIS->value => 2,
        ServiceOrderStatus::RECEIVED->value => 3,
    ];

    /**
     * Statuses excluded from the default listing (soft-exclude, not physical delete).
     *
     * @return ServiceOrderStatus[]
     */
    public static function excludedStatuses(): array
    {
        return [ServiceOrderStatus::FINALIZED, ServiceOrderStatus::DELIVERED];
    }

    /**
     * Sorts by status priority (in_execution > awaiting_approval > in_diagnosis > received),
     * oldest first within the same priority. Statuses outside the priority map sort last.
     *
     * @param  iterable<ServiceOrder>  $orders
     * @return Collection<int, ServiceOrder>
     */
    public static function sort(iterable $orders): Collection
    {
        return collect($orders)
            ->sort(fn (ServiceOrder $a, ServiceOrder $b) => self::rank($a) <=> self::rank($b)
                ?: $a->created_at <=> $b->created_at)
            ->values();
    }

    private static function rank(ServiceOrder $order): int
    {
        return self::PRIORITY[$order->status->value] ?? 99;
    }
}
