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
 * Saga steps 2-3: budget created/approved/rejected in the Billing Service,
 * then the local stock reservation before the repair is queued.
 */
class BudgetPhase
{
    public function __construct(
        private readonly SagaStepTracker $tracker,
        private readonly SagaCompensator $compensator,
        private readonly ServiceOrderStatusTransition $transition,
        private readonly SagaCommandSender $sender,
        private readonly StockReserver $stock,
        private readonly MessagingServiceInterface $notifier,
    ) {}

    public function onCreated(SagaInstance $saga, MessageEnvelope $message): void
    {
        $this->tracker->mark($saga, SagaStepName::CREATE_BUDGET, SagaStepStatus::SUCCEEDED, $message->payload);
        $order = $saga->serviceOrder;
        $this->transition->apply($order, ServiceOrderStatus::AWAITING_APPROVAL, 'orçamento gerado', [
            'total_amount' => $message->payloadValue('total'),
            'budget_sent_at' => now(),
        ]);
        $this->notifier->notifyBudgetReady($order->load(['client', 'vehicle']));
    }

    public function onCreationFailed(SagaInstance $saga, MessageEnvelope $message): void
    {
        $this->tracker->mark($saga, SagaStepName::CREATE_BUDGET, SagaStepStatus::FAILED, $message->payload);
        $this->compensator->compensate($saga, 'falha ao gerar orçamento: '.($message->payload['reason'] ?? 'desconhecido'));
    }

    public function onApproved(SagaInstance $saga, MessageEnvelope $message): void
    {
        $shortages = $this->stock->reserve($saga->serviceOrder);

        if ($shortages !== []) {
            $this->tracker->mark($saga, SagaStepName::RESERVE_STOCK, SagaStepStatus::FAILED, ['shortages' => $shortages]);
            $this->compensator->compensate($saga, 'estoque insuficiente: '.implode('; ', $shortages));

            return;
        }

        $this->tracker->mark($saga, SagaStepName::RESERVE_STOCK, SagaStepStatus::SUCCEEDED);
        $this->sender->sendStep($saga, SagaStepName::ENQUEUE_REPAIR, SagaMessage::ENQUEUE_REPAIR, [
            'service_order_id' => $saga->service_order_id,
            'service_order_number' => $saga->serviceOrder->number,
        ]);
    }

    public function onRejected(SagaInstance $saga, MessageEnvelope $message): void
    {
        $this->compensator->compensate($saga, 'orçamento rejeitado pelo cliente');
    }
}
