<?php

namespace Tests\Unit;

use App\Application\ServiceOrder\GetServiceOrderStatus;
use App\Infrastructure\Persistence\Eloquent\EloquentServiceOrderRepository;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetServiceOrderStatusTest extends TestCase
{
    use RefreshDatabase;

    private GetServiceOrderStatus $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useCase = new GetServiceOrderStatus(new EloquentServiceOrderRepository);
    }

    public function test_returns_the_order_with_its_current_status(): void
    {
        $client = User::factory()->client()->create();
        $vehicle = Vehicle::factory()->create(['client_id' => $client->id]);
        $order = ServiceOrder::factory()->create([
            'client_id' => $client->id,
            'vehicle_id' => $vehicle->id,
        ]);

        $found = $this->useCase->execute($order->id);

        $this->assertSame($order->id, $found->id);
        $this->assertSame('received', $found->status->value);
    }

    public function test_throws_when_order_does_not_exist(): void
    {
        $this->expectException(ModelNotFoundException::class);
        $this->useCase->execute(999);
    }
}
