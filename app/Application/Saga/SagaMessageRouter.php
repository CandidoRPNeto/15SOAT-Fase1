<?php

namespace App\Application\Saga;

use App\Application\Ports\MessageRouter;
use App\Domain\Messaging\MessageEnvelope;
use App\Domain\Saga\SagaMessage;
use App\Models\SagaInstance;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Routes Billing/Execution replies to the saga phase that owns them.
 * Messages for a finished saga are ignored (late replies after compensation).
 */
class SagaMessageRouter implements MessageRouter
{
    public function __construct(
        private readonly DiagnosisPhase $diagnosis,
        private readonly BudgetPhase $budget,
        private readonly RepairPhase $repair,
        private readonly PaymentPhase $payment,
    ) {}

    public function bindings(): array
    {
        return ['evt.execution.*', 'evt.billing.*'];
    }

    public function route(MessageEnvelope $envelope): void
    {
        $type = SagaMessage::tryFrom($envelope->type)
            ?? throw new InvalidArgumentException("Unsupported message type '{$envelope->type}'; expected one of the evt.* cases of ".SagaMessage::class);
        $saga = SagaInstance::with('serviceOrder')->findOrFail($envelope->sagaId);

        if ($saga->status->isFinished()) {
            Log::info('saga.late_message_ignored', ['saga_id' => $saga->id, 'type' => $envelope->type, 'saga_status' => $saga->status->value]);

            return;
        }

        $this->dispatch($type, $saga, $envelope);
    }

    private function dispatch(SagaMessage $type, SagaInstance $saga, MessageEnvelope $envelope): void
    {
        match ($type) {
            SagaMessage::DIAGNOSIS_ENQUEUED => $this->diagnosis->onEnqueued($saga, $envelope),
            SagaMessage::DIAGNOSIS_ENQUEUE_FAILED => $this->diagnosis->onEnqueueFailed($saga, $envelope),
            SagaMessage::DIAGNOSIS_STARTED => $this->diagnosis->onStarted($saga, $envelope),
            SagaMessage::DIAGNOSIS_COMPLETED => $this->diagnosis->onCompleted($saga, $envelope),
            SagaMessage::BUDGET_CREATED => $this->budget->onCreated($saga, $envelope),
            SagaMessage::BUDGET_CREATION_FAILED => $this->budget->onCreationFailed($saga, $envelope),
            SagaMessage::BUDGET_APPROVED => $this->budget->onApproved($saga, $envelope),
            SagaMessage::BUDGET_REJECTED => $this->budget->onRejected($saga, $envelope),
            SagaMessage::REPAIR_ENQUEUED => $this->repair->onEnqueued($saga, $envelope),
            SagaMessage::REPAIR_ENQUEUE_FAILED => $this->repair->onEnqueueFailed($saga, $envelope),
            SagaMessage::REPAIR_STARTED => $this->repair->onStarted($saga, $envelope),
            SagaMessage::REPAIR_FINISHED => $this->repair->onFinished($saga, $envelope),
            SagaMessage::PAYMENT_REQUESTED => $this->payment->onRequested($saga, $envelope),
            SagaMessage::PAYMENT_CONFIRMED => $this->payment->onConfirmed($saga, $envelope),
            default => throw new InvalidArgumentException("Message type '{$type->value}' is a command the OS Service sends, not consumes"),
        };
    }
}
