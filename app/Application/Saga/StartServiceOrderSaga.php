<?php

namespace App\Application\Saga;

use App\Domain\Saga\SagaMessage;
use App\Domain\Saga\SagaStatus;
use App\Domain\Saga\SagaStepName;
use App\Models\SagaInstance;
use App\Models\ServiceOrder;
use Illuminate\Support\Facades\Log;

/**
 * Saga step 1: runs inside the order-opening transaction, so the order, the
 * saga and the first command are stored atomically.
 *
 * Example: $start->execute($order, $request->attributes->get('correlation_id'));
 */
class StartServiceOrderSaga
{
    public function __construct(
        private readonly SagaCommandSender $sender,
        private readonly ServiceOrderStatusTransition $transition,
    ) {}

    public function execute(ServiceOrder $order, string $correlationId): SagaInstance
    {
        $saga = SagaInstance::create([
            'service_order_id' => $order->id,
            'status' => SagaStatus::RUNNING,
            'correlation_id' => $correlationId,
        ]);

        $this->transition->recordInitial($order, 'OS aberta');
        $this->sender->sendStep($saga, SagaStepName::ENQUEUE_DIAGNOSIS, SagaMessage::ENQUEUE_DIAGNOSIS, [
            'service_order_id' => $order->id,
            'service_order_number' => $order->number,
            'vehicle_id' => $order->vehicle_id,
            'notes' => $order->notes,
        ]);
        Log::info('saga.started', ['saga_id' => $saga->id, 'service_order_id' => $order->id]);

        return $saga;
    }
}
