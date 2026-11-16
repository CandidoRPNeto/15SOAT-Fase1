<?php

namespace App\Application\Saga;

use App\Domain\Saga\SagaStepName;
use App\Domain\Saga\SagaStepStatus;
use App\Models\SagaInstance;
use App\Models\SagaStep;
use Illuminate\Support\Facades\Log;

/**
 * Records step outcomes. Local steps (no command) are created on first mark.
 *
 * Example: $tracker->mark($saga, SagaStepName::CREATE_BUDGET, SagaStepStatus::SUCCEEDED, ['budget_id' => 'b-1']);
 */
class SagaStepTracker
{
    /** @param array<string, mixed> $detail */
    public function mark(SagaInstance $saga, SagaStepName $step, SagaStepStatus $status, array $detail = []): SagaStep
    {
        $record = SagaStep::firstOrNew(['saga_instance_id' => $saga->id, 'name' => $step]);
        $record->status = $status;
        $record->detail = [...($record->detail ?? []), ...$detail];
        $record->save();

        Log::info('saga.step', ['saga_id' => $saga->id, 'step' => $step->value, 'status' => $status->value, 'detail' => $detail]);

        return $record;
    }

    public function find(SagaInstance $saga, SagaStepName $step): ?SagaStep
    {
        return SagaStep::where('saga_instance_id', $saga->id)->where('name', $step)->first();
    }
}
