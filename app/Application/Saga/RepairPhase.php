<?php

namespace App\Application\Saga;

use App\Contracts\MessagingServiceInterface;
use App\Domain\Messaging\MessageEnvelope;
use App\Domain\Saga\SagaMessage;
use App\Domain\Saga\SagaStepName;
use App\Domain\Saga\SagaStepStatus;
use App\Domain\ServiceOrder\ServiceOrderStatus;
use App\Models\SagaInstance;

/**
 * Saga steps 3-5: repair queued, started (pivot — no compensation after it)
 * and finished, which triggers the payment request.
 */
class RepairPhase
{
    public function __construct(
        private readonly SagaStepTracker $tracker,
        private readonly SagaCompensator $compensator,
        private readonly ServiceOrderStatusTransition $transition,
        private readonly SagaCommandSender $sender,
        private readonly MessagingServiceInterface $notifier,
    ) {}

    public function onEnqueued(SagaInstance $saga, MessageEnvelope $message): void
    {
        $this->tracker->mark($saga, SagaStepName::ENQUEUE_REPAIR, SagaStepStatus::SUCCEEDED, $message->payload);
        $this->transition->apply($saga->serviceOrder, ServiceOrderStatus::APPROVED, 'orçamento aprovado; reparo na fila');
    }

    public function onEnqueueFailed(SagaInstance $saga, MessageEnvelope $message): void
    {
        $this->tracker->mark($saga, SagaStepName::ENQUEUE_REPAIR, SagaStepStatus::FAILED, $message->payload);
        $this->compensator->compensate($saga, 'falha ao enfileirar reparo: '.($message->payload['reason'] ?? 'desconhecido'));
    }

    public function onStarted(SagaInstance $saga, MessageEnvelope $message): void
    {
        $this->transition->apply($saga->serviceOrder, ServiceOrderStatus::IN_EXECUTION, 'reparo iniciado pelo mecânico');
    }

    public function onFinished(SagaInstance $saga, MessageEnvelope $message): void
    {
        $order = $saga->serviceOrder;
        $this->transition->apply($order, ServiceOrderStatus::FINALIZED, 'reparo finalizado', ['finalized_at' => now()]);
        $this->notifier->notifyPickupReady($order->load(['client', 'vehicle']));

        $this->sender->sendStep($saga, SagaStepName::REQUEST_PAYMENT, SagaMessage::REQUEST_PAYMENT, [
            'service_order_id' => $order->id,
            'service_order_number' => $order->number,
            'client_id' => $order->client_id,
            'amount' => (float) $order->total_amount,
        ]);
    }
}
