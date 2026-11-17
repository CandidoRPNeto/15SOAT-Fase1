<?php

namespace App\Application\Saga;

use App\Domain\ServiceOrder\Exceptions\InvalidStatusTransitionException;
use App\Domain\ServiceOrder\ServiceOrderStatus;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderStatusHistory;
use Illuminate\Support\Facades\Log;

/**
 * The only place that changes a service order's status. Validates the
 * transition and appends to service_order_status_history.
 *
 * Example: $transition->apply($order, ServiceOrderStatus::IN_DIAGNOSIS, 'diagnóstico iniciado');
 */
class ServiceOrderStatusTransition
{
    /** @param array<string, mixed> $extraAttributes */
    public function apply(ServiceOrder $order, ServiceOrderStatus $to, string $reason, array $extraAttributes = []): void
    {
        $from = $order->status;

        if (! $from->canTransitionTo($to)) {
            throw new InvalidStatusTransitionException(
                "OS {$order->number}: transition {$from->value} -> {$to->value} not allowed; expected one of: "
                .implode(', ', array_map(fn (ServiceOrderStatus $s) => $s->value, $from->allowedTransitions()))
            );
        }

        $order->update(['status' => $to, ...$extraAttributes]);
        $this->record($order, $from, $to, $reason);
    }

    public function recordInitial(ServiceOrder $order, string $reason): void
    {
        $this->record($order, null, $order->status, $reason);
    }

    private function record(ServiceOrder $order, ?ServiceOrderStatus $from, ServiceOrderStatus $to, string $reason): void
    {
        ServiceOrderStatusHistory::create([
            'service_order_id' => $order->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'reason' => $reason,
        ]);
        Log::info('service_order.status_changed', [
            'service_order_id' => $order->id, 'from' => $from?->value, 'to' => $to->value, 'reason' => $reason,
        ]);
    }
}
