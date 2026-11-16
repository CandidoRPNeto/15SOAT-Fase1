<?php

namespace App\Application\Saga;

use App\Application\Messaging\OutboxRecorder;
use App\Domain\Messaging\MessageEnvelope;
use App\Domain\Saga\SagaMessage;
use App\Domain\Saga\SagaStepName;
use App\Domain\Saga\SagaStepStatus;
use App\Models\SagaInstance;
use App\Models\SagaStep;

/**
 * Sends saga commands through the outbox and tracks the step they belong to.
 *
 * Example: $sender->sendStep($saga, SagaStepName::CREATE_BUDGET, SagaMessage::CREATE_BUDGET, $payload);
 */
class SagaCommandSender
{
    public function __construct(private readonly OutboxRecorder $outbox) {}

    /** @param array<string, mixed> $payload */
    public function sendStep(SagaInstance $saga, SagaStepName $step, SagaMessage $command, array $payload): void
    {
        $this->record($saga, $command->value, $payload);

        SagaStep::updateOrCreate(
            ['saga_instance_id' => $saga->id, 'name' => $step],
            [
                'status' => SagaStepStatus::PENDING,
                'command_type' => $command->value,
                'command_payload' => $payload,
                'attempts' => 1,
                'sent_at' => now(),
            ],
        );
    }

    /** Re-sends a pending step's command with a new message id (receivers are idempotent by business key). */
    public function resend(SagaStep $step): void
    {
        $this->record($step->sagaInstance, (string) $step->command_type, $step->command_payload ?? []);
        $step->update(['attempts' => $step->attempts + 1, 'sent_at' => now()]);
    }

    /** @param array<string, mixed> $payload */
    public function sendCompensation(SagaInstance $saga, SagaMessage $command, array $payload): void
    {
        $this->record($saga, $command->value, $payload);
    }

    /** @param array<string, mixed> $payload */
    private function record(SagaInstance $saga, string $type, array $payload): void
    {
        $this->outbox->record(MessageEnvelope::create($type, $saga->id, $saga->correlation_id, $payload));
    }
}
