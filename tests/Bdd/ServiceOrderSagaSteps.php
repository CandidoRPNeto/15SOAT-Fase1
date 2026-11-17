<?php

namespace Tests\Bdd;

use App\Application\Saga\SagaWatchdog;
use App\Domain\Saga\SagaMessage;
use App\Models\Item;
use App\Models\SagaStep;
use App\Models\ServiceOrder;
use App\Models\User;
use PHPUnit\Framework\Assert;
use Tests\Support\SagaDriver;
use Tests\TestCase;

/**
 * Step definitions for features/saga_ordem_de_servico.feature.
 * Billing/Execution replies are injected through the real InboxProcessor.
 */
final class ServiceOrderSagaSteps
{
    private SagaDriver $driver;

    private ?ServiceOrder $order = null;

    /** @var array<string, Item> */
    private array $items = [];

    public function __construct(private readonly TestCase $test)
    {
        $this->driver = new SagaDriver;
    }

    #[Step('uma peça "([^"]+)" de R\$ ([\d.]+) com (\d+) unidades em estoque')]
    public function anItemInStock(string $name, string $price, string $stock): void
    {
        $this->items[$name] = Item::factory()->create(['name' => $name, 'price' => (float) $price, 'stock_quantity' => (int) $stock]);
    }

    #[Step('a recepção abre uma OS com (\d+) unidades de "([^"]+)"')]
    public function receptionOpensOrder(string $quantity, string $name): void
    {
        $this->order = $this->driver->openOrder([$this->items[$name]->id => (int) $quantity]);
    }

    #[Step('o (?:Execution|Billing) responde "([^"]+)"')]
    public function serviceReplies(string $type): void
    {
        $this->driver->reply($this->order(), SagaMessage::from($type));
    }

    #[Step('o Execution recusa com "([^"]+)" pelo motivo "([^"]+)"')]
    public function executionRefuses(string $type, string $reason): void
    {
        $this->driver->reply($this->order(), SagaMessage::from($type), ['reason' => $reason]);
    }

    #[Step('o Billing responde que o orçamento de R\$ ([\d.]+) foi criado')]
    public function billingCreatedBudget(string $total): void
    {
        $this->driver->reply($this->order(), SagaMessage::BUDGET_CREATED, ['budget_id' => 'b-bdd', 'total' => (float) $total]);
    }

    #[Step('o cliente aprova o orçamento no Billing')]
    public function clientApproves(): void
    {
        $this->driver->reply($this->order(), SagaMessage::BUDGET_APPROVED);
    }

    #[Step('a OS avança até o orçamento aguardando aprovação')]
    public function advanceToApproval(): void
    {
        $this->driver->advanceToAwaitingApproval($this->order());
    }

    #[Step('a recepção entrega o veículo')]
    public function receptionDelivers(): void
    {
        $this->test->actingAs(User::factory()->receptionist()->create())
            ->postJson("/api/v1/service-orders/{$this->order()->id}/deliver")
            ->assertOk();
    }

    #[Step('o Execution fica sem responder enquanto o watchdog roda (\d+) vezes')]
    public function executionSilentWhileWatchdogRuns(string $runs): void
    {
        for ($i = 0; $i < (int) $runs; $i++) {
            SagaStep::where('status', 'pending')->update(['sent_at' => now()->subHour()]);
            app(SagaWatchdog::class)->run(60, 3);
        }
    }

    #[Step('a OS fica com status "([^"]+)"')]
    public function orderHasStatus(string $status): void
    {
        Assert::assertSame($status, $this->order()->refresh()->status->value);
    }

    #[Step('a saga fica com status "([^"]+)"')]
    public function sagaHasStatus(string $status): void
    {
        Assert::assertSame($status, $this->driver->saga($this->order())->status->value);
    }

    #[Step('o comando "([^"]+)" é enviado')]
    public function commandSent(string $type): void
    {
        Assert::assertContains($type, $this->driver->sentCommands($this->order()));
    }

    #[Step('o comando "([^"]+)" é enviado com total de R\$ ([\d.]+)')]
    public function commandSentWithTotal(string $type, string $total): void
    {
        $step = SagaStep::where('command_type', $type)->firstOrFail();
        Assert::assertEqualsWithDelta((float) $total, (float) $step->command_payload['total'], 0.001);
    }

    #[Step('o comando "([^"]+)" foi enviado (\d+) vezes')]
    public function commandSentTimes(string $type, string $times): void
    {
        $sent = array_count_values($this->driver->sentCommands($this->order()));
        Assert::assertSame((int) $times, $sent[$type] ?? 0);
    }

    #[Step('o estoque de "([^"]+)" fica com (\d+) unidades')]
    public function stockIs(string $name, string $quantity): void
    {
        Assert::assertSame((int) $quantity, $this->items[$name]->refresh()->stock_quantity);
    }

    #[Step('o motivo da compensação contém "([^"]+)"')]
    public function compensationReasonContains(string $text): void
    {
        Assert::assertStringContainsString($text, (string) $this->driver->saga($this->order())->failure_reason);
    }

    private function order(): ServiceOrder
    {
        return $this->order ?? throw new \LogicException('No service order opened yet; start the scenario with "a recepção abre uma OS ..."');
    }
}
