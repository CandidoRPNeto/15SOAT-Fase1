<?php

namespace App\Application\Saga;

use App\Domain\Messaging\MessageEnvelope;
use App\Domain\Saga\SagaMessage;
use App\Domain\Saga\SagaStepName;
use App\Domain\Saga\SagaStepStatus;
use App\Domain\ServiceOrder\ServiceOrderStatus;
use App\Models\SagaInstance;

/**
 * Saga steps 1-2: diagnosis queued/started/completed by the Execution Service.
 */
class DiagnosisPhase
{
    public function __construct(
        private readonly SagaStepTracker $tracker,
        private readonly SagaCompensator $compensator,
        private readonly ServiceOrderStatusTransition $transition,
        private readonly SagaCommandSender $sender,
        private readonly BudgetSnapshot $snapshot,
    ) {}

    public function onEnqueued(SagaInstance $saga, MessageEnvelope $message): void
    {
        $this->tracker->mark($saga, SagaStepName::ENQUEUE_DIAGNOSIS, SagaStepStatus::SUCCEEDED, $message->payload);
    }

    public function onEnqueueFailed(SagaInstance $saga, MessageEnvelope $message): void
    {
        $this->tracker->mark($saga, SagaStepName::ENQUEUE_DIAGNOSIS, SagaStepStatus::FAILED, $message->payload);
        $this->compensator->compensate($saga, 'falha ao enfileirar diagnóstico: '.($message->payload['reason'] ?? 'desconhecido'));
    }

    public function onStarted(SagaInstance $saga, MessageEnvelope $message): void
    {
        $this->transition->apply($saga->serviceOrder, ServiceOrderStatus::IN_DIAGNOSIS, 'diagnóstico iniciado pelo mecânico');
    }

    public function onCompleted(SagaInstance $saga, MessageEnvelope $message): void
    {
        $payload = [...$this->snapshot->forOrder($saga->serviceOrder), 'diagnosis_notes' => $message->payload['notes'] ?? null];
        $this->sender->sendStep($saga, SagaStepName::CREATE_BUDGET, SagaMessage::CREATE_BUDGET, $payload);
    }
}
