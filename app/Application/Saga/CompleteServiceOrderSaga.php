<?php

namespace App\Application\Saga;

use App\Domain\Saga\SagaStatus;
use App\Models\SagaInstance;
use App\Models\ServiceOrder;
use Illuminate\Support\Facades\Log;

/**
 * Closes the saga when the receptionist delivers the vehicle.
 *
 * Example: $complete->execute($order);
 */
class CompleteServiceOrderSaga
{
    public function execute(ServiceOrder $order): void
    {
        $saga = SagaInstance::where('service_order_id', $order->id)->first();

        if ($saga === null || $saga->status->isFinished()) {
            return;
        }

        $saga->update(['status' => SagaStatus::COMPLETED]);
        Log::info('saga.completed', ['saga_id' => $saga->id, 'service_order_id' => $order->id]);
    }
}
