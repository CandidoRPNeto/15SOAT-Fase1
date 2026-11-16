<?php

namespace Tests\Feature;

use App\Domain\Saga\SagaMessage;
use App\Models\SagaStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SagaDriver;
use Tests\TestCase;

class ServiceOrderSagaTest extends TestCase
{
    use RefreshDatabase;

    private SagaDriver $saga;

    protected function setUp(): void
    {
        parent::setUp();
        $this->saga = new SagaDriver;
    }

    public function test_happy_path_drives_order_from_received_to_paid(): void
    {
        $item = $this->saga->itemWithStock(5);
        $order = $this->saga->openOrder([$item->id => 2]);

        $this->saga->advanceToAwaitingApproval($order, 150.0);
        $this->assertSame('awaiting_approval', $order->refresh()->status->value);
        $this->assertSame('150.00', $order->total_amount);

        $this->saga->reply($order, SagaMessage::BUDGET_APPROVED);
        $this->assertSame(3, $item->refresh()->stock_quantity);
        $this->saga->reply($order, SagaMessage::REPAIR_ENQUEUED);
        $this->assertSame('approved', $order->refresh()->status->value);

        $this->saga->reply($order, SagaMessage::REPAIR_STARTED);
        $this->saga->reply($order, SagaMessage::REPAIR_FINISHED);
        $this->assertSame('finalized', $order->refresh()->status->value);
        $this->assertNotNull($order->finalized_at);

        $this->saga->reply($order, SagaMessage::PAYMENT_REQUESTED, ['payment_id' => 'p-1']);
        $this->saga->reply($order, SagaMessage::PAYMENT_CONFIRMED, ['payment_id' => 'p-1']);
        $this->assertTrue($order->refresh()->isPaid());

        $this->assertSame([
            'cmd.execution.enqueue_diagnosis',
            'cmd.billing.create_budget',
            'cmd.execution.enqueue_repair',
            'cmd.billing.request_payment',
        ], $this->saga->sentCommands($order));
        $this->assertSame('running', $this->saga->saga($order)->status->value);
    }

    public function test_budget_command_carries_a_price_snapshot_of_the_order_lines(): void
    {
        $item = $this->saga->itemWithStock(5, 40.0);
        $order = $this->saga->openOrder([$item->id => 2]);
        $this->saga->reply($order, SagaMessage::DIAGNOSIS_STARTED);
        $this->saga->reply($order, SagaMessage::DIAGNOSIS_COMPLETED, ['notes' => 'freio']);

        $step = SagaStep::where('name', 'create_budget')->firstOrFail();
        $this->assertSame(80.0, (float) $step->command_payload['total']);
        $this->assertSame('freio', $step->command_payload['diagnosis_notes']);
        $this->assertSame(2, $step->command_payload['lines'][0]['quantity']);
    }

    public function test_insufficient_stock_compensates_budget_task_and_order(): void
    {
        $item = $this->saga->itemWithStock(1);
        $order = $this->saga->openOrder([$item->id => 3]);
        $this->saga->advanceToAwaitingApproval($order);

        $this->saga->reply($order, SagaMessage::BUDGET_APPROVED);

        $this->assertSame('cancelled', $order->refresh()->status->value);
        $this->assertSame(1, $item->refresh()->stock_quantity);
        $saga = $this->saga->saga($order);
        $this->assertSame('compensated', $saga->status->value);
        $this->assertStringContainsString('estoque insuficiente', $saga->failure_reason);
        $this->assertContains('cmd.billing.void_budget', $this->saga->sentCommands($order));
        $this->assertContains('cmd.execution.cancel_task', $this->saga->sentCommands($order));
    }

    public function test_repair_enqueue_failure_releases_reserved_stock(): void
    {
        $item = $this->saga->itemWithStock(5);
        $order = $this->saga->openOrder([$item->id => 2]);
        $this->saga->advanceToAwaitingApproval($order);
        $this->saga->reply($order, SagaMessage::BUDGET_APPROVED);
        $this->assertSame(3, $item->refresh()->stock_quantity);

        $this->saga->reply($order, SagaMessage::REPAIR_ENQUEUE_FAILED, ['reason' => 'fila cheia']);

        $this->assertSame(5, $item->refresh()->stock_quantity);
        $this->assertSame('cancelled', $order->refresh()->status->value);
        $this->assertDatabaseHas('saga_steps', ['name' => 'reserve_stock', 'status' => 'compensated']);
        $this->assertDatabaseHas('saga_steps', ['name' => 'enqueue_repair', 'status' => 'failed']);
    }

    public function test_budget_rejected_by_client_cancels_order(): void
    {
        $order = $this->saga->openOrder();
        $this->saga->advanceToAwaitingApproval($order);

        $this->saga->reply($order, SagaMessage::BUDGET_REJECTED);

        $this->assertSame('cancelled', $order->refresh()->status->value);
        $this->assertSame(['cmd.execution.enqueue_diagnosis', 'cmd.billing.create_budget', 'cmd.billing.void_budget', 'cmd.execution.cancel_task'], $this->saga->sentCommands($order));
    }

    public function test_diagnosis_enqueue_failure_cancels_a_received_order(): void
    {
        $order = $this->saga->openOrder();

        $this->saga->reply($order, SagaMessage::DIAGNOSIS_ENQUEUE_FAILED, ['reason' => 'sem mecânico']);

        $this->assertSame('cancelled', $order->refresh()->status->value);
        $this->assertStringContainsString('sem mecânico', $this->saga->saga($order)->failure_reason);
    }

    public function test_budget_creation_failure_cancels_order_in_diagnosis(): void
    {
        $order = $this->saga->openOrder();
        $this->saga->reply($order, SagaMessage::DIAGNOSIS_ENQUEUED);
        $this->saga->reply($order, SagaMessage::DIAGNOSIS_STARTED);
        $this->saga->reply($order, SagaMessage::DIAGNOSIS_COMPLETED);

        $this->saga->reply($order, SagaMessage::BUDGET_CREATION_FAILED, ['reason' => 'orçamento sem itens']);

        $this->assertSame('cancelled', $order->refresh()->status->value);
        $this->assertDatabaseHas('saga_steps', ['name' => 'create_budget', 'status' => 'failed']);
        $this->assertContains('cmd.execution.cancel_task', $this->saga->sentCommands($order));
    }

    public function test_late_replies_after_compensation_are_ignored(): void
    {
        $order = $this->saga->openOrder();
        $this->saga->reply($order, SagaMessage::DIAGNOSIS_ENQUEUE_FAILED);

        $this->assertTrue($this->saga->reply($order, SagaMessage::DIAGNOSIS_STARTED));
        $this->assertSame('cancelled', $order->refresh()->status->value);
    }

    public function test_duplicate_payment_confirmation_is_harmless(): void
    {
        $order = $this->saga->openOrder();
        $order->update(['paid_at' => now()->subDay()]);
        $paidAt = $order->refresh()->paid_at;

        $this->saga->reply($order, SagaMessage::PAYMENT_CONFIRMED);

        $this->assertEquals($paidAt, $order->refresh()->paid_at);
    }
}
