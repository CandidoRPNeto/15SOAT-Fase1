<?php

namespace App\Application\Saga;

use App\Domain\Messaging\MessageEnvelope;
use App\Domain\Saga\SagaStepName;
use App\Domain\Saga\SagaStepStatus;
use App\Models\SagaInstance;
use Illuminate\Support\Facades\Log;

/**
 * Saga steps 5-6: payment requested (Mercado Pago checkout created by
 * Billing) and confirmed. Retry-only: the repair is already done.
 */
class PaymentPhase
{
    public function __construct(private readonly SagaStepTracker $tracker) {}

    public function onRequested(SagaInstance $saga, MessageEnvelope $message): void
    {
        $this->tracker->mark($saga, SagaStepName::REQUEST_PAYMENT, SagaStepStatus::SUCCEEDED, $message->payload);
    }

    public function onConfirmed(SagaInstance $saga, MessageEnvelope $message): void
    {
        $order = $saga->serviceOrder;

        if ($order->isPaid()) {
            return;
        }

        $order->update(['paid_at' => now()]);
        Log::info('service_order.paid', ['service_order_id' => $order->id, 'payment' => $message->payload]);
    }
}
