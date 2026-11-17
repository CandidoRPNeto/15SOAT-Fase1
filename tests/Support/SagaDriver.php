<?php

namespace Tests\Support;

use App\Application\Messaging\InboxProcessor;
use App\Application\ServiceOrder\OpenServiceOrder;
use App\Domain\Messaging\MessageEnvelope;
use App\Domain\Saga\SagaMessage;
use App\Models\Item;
use App\Models\OutboxMessage;
use App\Models\SagaInstance;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\Vehicle;

/**
 * Drives the saga in-process: opens orders and feeds Billing/Execution
 * replies through the real InboxProcessor. Shared by PHPUnit and BDD tests.
 */
class SagaDriver
{
    /** @param array<int, int> $itemQuantities item_id => quantity */
    public function openOrder(array $itemQuantities = []): ServiceOrder
    {
        $client = User::factory()->client()->create();
        $vehicle = Vehicle::factory()->create(['client_id' => $client->id]);
        $items = array_map(fn (int $id, int $qty) => ['item_id' => $id, 'quantity' => $qty], array_keys($itemQuantities), $itemQuantities);

        return app(OpenServiceOrder::class)->execute(
            ['client_id' => $client->id, 'vehicle_id' => $vehicle->id, 'items' => $items],
            'corr-'.uniqid(),
        );
    }

    public function itemWithStock(int $stock, float $price = 50.0): Item
    {
        return Item::factory()->create(['stock_quantity' => $stock, 'price' => $price]);
    }

    /** @param array<string, mixed> $payload */
    public function reply(ServiceOrder $order, SagaMessage $type, array $payload = []): bool
    {
        $envelope = MessageEnvelope::create($type->value, $this->saga($order)->id, 'corr-reply', [
            'service_order_id' => $order->id,
            ...$payload,
        ]);

        return app(InboxProcessor::class)->process($envelope);
    }

    public function saga(ServiceOrder $order): SagaInstance
    {
        return SagaInstance::where('service_order_id', $order->id)->firstOrFail();
    }

    /** @return list<string> command types recorded in the outbox for this order's saga */
    public function sentCommands(ServiceOrder $order): array
    {
        $sagaId = $this->saga($order)->id;

        return OutboxMessage::orderBy('id')->get()
            ->filter(fn (OutboxMessage $m) => $m->body['saga_id'] === $sagaId)
            ->map(fn (OutboxMessage $m) => $m->type)
            ->values()->all();
    }

    /** Runs the happy path up to the budget awaiting approval. */
    public function advanceToAwaitingApproval(ServiceOrder $order, float $total = 100.0): void
    {
        $this->reply($order, SagaMessage::DIAGNOSIS_ENQUEUED, ['task_id' => 't-1']);
        $this->reply($order, SagaMessage::DIAGNOSIS_STARTED);
        $this->reply($order, SagaMessage::DIAGNOSIS_COMPLETED, ['notes' => 'troca de pastilhas']);
        $this->reply($order, SagaMessage::BUDGET_CREATED, ['budget_id' => 'b-1', 'total' => $total]);
    }
}
