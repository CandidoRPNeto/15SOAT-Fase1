<?php

namespace App\Application\Saga;

use App\Domain\Saga\SagaStatus;
use App\Domain\Saga\SagaStepStatus;
use App\Models\SagaStep;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handles steps whose reply never arrived (service down, message lost):
 * re-sends up to $maxAttempts, then fails the step and compensates.
 *
 * Example: $watchdog->run(timeoutSeconds: 120, maxAttempts: 3);
 */
class SagaWatchdog
{
    public function __construct(
        private readonly SagaCommandSender $sender,
        private readonly SagaStepTracker $tracker,
        private readonly SagaCompensator $compensator,
    ) {}

    /** @return array{resent: int, compensated: int} */
    public function run(int $timeoutSeconds, int $maxAttempts): array
    {
        $result = ['resent' => 0, 'compensated' => 0];

        foreach ($this->staleSteps($timeoutSeconds) as $step) {
            $outcome = DB::transaction(fn () => $this->handle($step, $maxAttempts));
            $result[$outcome]++;
        }

        return $result;
    }

    /** @return Collection<int, SagaStep> */
    private function staleSteps(int $timeoutSeconds)
    {
        return SagaStep::with('sagaInstance.serviceOrder')
            ->where('status', SagaStepStatus::PENDING)
            ->where('sent_at', '<', now()->subSeconds($timeoutSeconds))
            ->whereHas('sagaInstance', fn ($query) => $query->where('status', SagaStatus::RUNNING))
            ->get();
    }

    /** @return 'resent'|'compensated' */
    private function handle(SagaStep $step, int $maxAttempts): string
    {
        if ($step->attempts < $maxAttempts || ! $step->name->isCompensable()) {
            $this->sender->resend($step);
            Log::warning('saga.step_resent', ['saga_id' => $step->saga_instance_id, 'step' => $step->name->value, 'attempt' => $step->attempts]);

            return 'resent';
        }

        $saga = $step->sagaInstance;
        $this->tracker->mark($saga, $step->name, SagaStepStatus::FAILED, ['reason' => 'timeout']);
        $this->compensator->compensate($saga, "timeout no passo {$step->name->value} após {$step->attempts} tentativas");

        return 'compensated';
    }
}
