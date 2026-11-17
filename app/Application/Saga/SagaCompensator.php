<?php

namespace App\Application\Saga;

use App\Domain\Saga\SagaMessage;
use App\Domain\Saga\SagaStatus;
use App\Domain\Saga\SagaStepName;
use App\Domain\Saga\SagaStepStatus;
use App\Domain\ServiceOrder\ServiceOrderStatus;
use App\Models\SagaInstance;
use App\Models\SagaStep;
use Illuminate\Support\Facades\Log;

/**
 * Undoes every compensable step already sent (pending or succeeded), in
 * reverse order, then cancels the order (ADR-010). Idempotent.
 *
 * Example: $compensator->compensate($saga, 'estoque insuficiente');
 */
class SagaCompensator
{
    public function __construct(
        private readonly SagaCommandSender $sender,
        private readonly SagaStepTracker $tracker,
        private readonly StockReserver $stock,
        private readonly ServiceOrderStatusTransition $transition,
    ) {}

    public function compensate(SagaInstance $saga, string $reason): void
    {
        if ($saga->status->isFinished()) {
            return;
        }

        $this->releaseStock($saga);
        $this->voidBudget($saga, $reason);
        $this->cancelExecutionTask($saga, $reason);
        $this->cancelOrder($saga, $reason);

        $saga->update(['status' => SagaStatus::COMPENSATED, 'failure_reason' => $reason]);
        Log::warning('saga.compensated', ['saga_id' => $saga->id, 'service_order_id' => $saga->service_order_id, 'reason' => $reason]);
    }

    private function releaseStock(SagaInstance $saga): void
    {
        if ($this->wasApplied($saga, SagaStepName::RESERVE_STOCK)) {
            $this->stock->release($saga->serviceOrder);
            $this->tracker->mark($saga, SagaStepName::RESERVE_STOCK, SagaStepStatus::COMPENSATED);
        }
    }

    private function voidBudget(SagaInstance $saga, string $reason): void
    {
        if ($this->wasApplied($saga, SagaStepName::CREATE_BUDGET)) {
            $this->sender->sendCompensation($saga, SagaMessage::VOID_BUDGET, $this->payload($saga, $reason));
            $this->tracker->mark($saga, SagaStepName::CREATE_BUDGET, SagaStepStatus::COMPENSATED);
        }
    }

    private function cancelExecutionTask(SagaInstance $saga, string $reason): void
    {
        $steps = array_filter(
            [SagaStepName::ENQUEUE_REPAIR, SagaStepName::ENQUEUE_DIAGNOSIS],
            fn (SagaStepName $step) => $this->wasApplied($saga, $step),
        );

        if ($steps === []) {
            return;
        }

        $this->sender->sendCompensation($saga, SagaMessage::CANCEL_TASK, $this->payload($saga, $reason));
        foreach ($steps as $step) {
            $this->tracker->mark($saga, $step, SagaStepStatus::COMPENSATED);
        }
    }

    private function cancelOrder(SagaInstance $saga, string $reason): void
    {
        $order = $saga->serviceOrder->refresh();

        if ($order->status->canTransitionTo(ServiceOrderStatus::CANCELLED)) {
            $this->transition->apply($order, ServiceOrderStatus::CANCELLED, "saga compensada: {$reason}");
        }
    }

    /** A step needs undoing if its command may have taken effect (pending) or did (succeeded). */
    private function wasApplied(SagaInstance $saga, SagaStepName $step): bool
    {
        $record = $this->tracker->find($saga, $step);

        return $record instanceof SagaStep
            && in_array($record->status, [SagaStepStatus::PENDING, SagaStepStatus::SUCCEEDED], true);
    }

    /** @return array{service_order_id: int, reason: string} */
    private function payload(SagaInstance $saga, string $reason): array
    {
        return ['service_order_id' => $saga->service_order_id, 'reason' => $reason];
    }
}
