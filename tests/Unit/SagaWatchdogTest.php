<?php

namespace Tests\Unit;

use App\Application\Saga\SagaWatchdog;
use App\Domain\Saga\SagaMessage;
use App\Models\SagaStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SagaDriver;
use Tests\TestCase;

class SagaWatchdogTest extends TestCase
{
    use RefreshDatabase;

    private function ageStep(string $name, int $attempts): void
    {
        SagaStep::where('name', $name)->update(['sent_at' => now()->subMinutes(10), 'attempts' => $attempts]);
    }

    public function test_resends_stale_step_below_max_attempts(): void
    {
        $order = (new SagaDriver)->openOrder();
        $this->ageStep('enqueue_diagnosis', 1);

        $result = app(SagaWatchdog::class)->run(60, 3);

        $this->assertSame(['resent' => 1, 'compensated' => 0], $result);
        $this->assertSame(2, SagaStep::where('name', 'enqueue_diagnosis')->value('attempts'));
        $this->assertSame(['cmd.execution.enqueue_diagnosis', 'cmd.execution.enqueue_diagnosis'], (new SagaDriver)->sentCommands($order));
    }

    public function test_compensates_after_max_attempts(): void
    {
        $order = (new SagaDriver)->openOrder();
        $this->ageStep('enqueue_diagnosis', 3);

        $result = app(SagaWatchdog::class)->run(60, 3);

        $this->assertSame(['resent' => 0, 'compensated' => 1], $result);
        $this->assertSame('cancelled', $order->refresh()->status->value);
        $this->assertStringContainsString('timeout no passo enqueue_diagnosis', (new SagaDriver)->saga($order)->failure_reason);
    }

    public function test_payment_step_is_only_retried_never_compensated(): void
    {
        $driver = new SagaDriver;
        $order = $driver->openOrder();
        $driver->advanceToAwaitingApproval($order);
        $driver->reply($order, SagaMessage::BUDGET_APPROVED);
        $driver->reply($order, SagaMessage::REPAIR_ENQUEUED);
        $driver->reply($order, SagaMessage::REPAIR_STARTED);
        $driver->reply($order, SagaMessage::REPAIR_FINISHED);
        $this->ageStep('request_payment', 5);

        $this->assertSame(['resent' => 1, 'compensated' => 0], app(SagaWatchdog::class)->run(60, 3));
        $this->assertSame('finalized', $order->refresh()->status->value);
    }

    public function test_fresh_steps_are_left_alone_and_command_reports_counts(): void
    {
        (new SagaDriver)->openOrder();

        $this->artisan('saga:watchdog')->expectsOutput('resent=0 compensated=0')->assertSuccessful();
    }
}
