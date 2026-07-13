<?php

namespace Tests\Unit;

use App\Application\ServiceOrder\ApproveBudget;
use App\Domain\ServiceOrder\Exceptions\InvalidStatusTransitionException;
use App\Infrastructure\Persistence\Eloquent\EloquentServiceOrderRepository;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApproveBudgetTest extends TestCase
{
    use RefreshDatabase;

    private ApproveBudget $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useCase = new ApproveBudget(new EloquentServiceOrderRepository);
    }

    private function makeAwaitingApprovalOrder(): ServiceOrder
    {
        $client = User::factory()->client()->create();
        $vehicle = Vehicle::factory()->create(['client_id' => $client->id]);

        return ServiceOrder::factory()->awaitingApproval()->create([
            'client_id' => $client->id,
            'vehicle_id' => $vehicle->id,
        ]);
    }

    public function test_approves_order_awaiting_approval(): void
    {
        $order = $this->makeAwaitingApprovalOrder();

        $result = $this->useCase->execute($order->number, true);

        $this->assertSame('approved', $result->status->value);
    }

    public function test_rejects_order_awaiting_approval(): void
    {
        $order = $this->makeAwaitingApprovalOrder();

        $result = $this->useCase->execute($order->number, false);

        $this->assertSame('cancelled', $result->status->value);
    }

    public function test_throws_when_order_does_not_exist(): void
    {
        $this->expectException(ModelNotFoundException::class);
        $this->useCase->execute('OS-0000-99999', true);
    }

    public function test_throws_when_order_is_not_awaiting_approval(): void
    {
        $client = User::factory()->client()->create();
        $vehicle = Vehicle::factory()->create(['client_id' => $client->id]);
        $order = ServiceOrder::factory()->received()->create([
            'client_id' => $client->id,
            'vehicle_id' => $vehicle->id,
        ]);

        $this->expectException(InvalidStatusTransitionException::class);
        $this->useCase->execute($order->number, true);
    }
}
