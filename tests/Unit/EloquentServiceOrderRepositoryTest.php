<?php

namespace Tests\Unit;

use App\Domain\ServiceOrder\ServiceOrderStatus;
use App\Infrastructure\Persistence\Eloquent\EloquentServiceOrderRepository;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EloquentServiceOrderRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentServiceOrderRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentServiceOrderRepository;
    }

    public function test_find_by_id_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->findById(999));
    }

    public function test_find_by_id_returns_existing_order(): void
    {
        $client = User::factory()->client()->create();
        $vehicle = Vehicle::factory()->create(['client_id' => $client->id]);
        $order = ServiceOrder::factory()->create([
            'client_id' => $client->id,
            'vehicle_id' => $vehicle->id,
        ]);

        $found = $this->repository->findById($order->id);

        $this->assertNotNull($found);
        $this->assertSame($order->id, $found->id);
    }

    public function test_find_by_number_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->findByNumber('OS-0000-99999'));
    }

    public function test_find_by_number_returns_existing_order(): void
    {
        $client = User::factory()->client()->create();
        $vehicle = Vehicle::factory()->create(['client_id' => $client->id]);
        $order = ServiceOrder::factory()->create([
            'client_id' => $client->id,
            'vehicle_id' => $vehicle->id,
        ]);

        $found = $this->repository->findByNumber($order->number);

        $this->assertNotNull($found);
        $this->assertSame($order->id, $found->id);
    }

    public function test_create_persists_a_new_order(): void
    {
        $client = User::factory()->client()->create();
        $vehicle = Vehicle::factory()->create(['client_id' => $client->id]);

        $order = $this->repository->create([
            'client_id' => $client->id,
            'vehicle_id' => $vehicle->id,
            'status' => ServiceOrderStatus::RECEIVED,
        ]);

        $this->assertNotNull($order->id);
        $this->assertDatabaseHas('service_orders', ['id' => $order->id]);
    }

    public function test_save_persists_changes_to_an_existing_order(): void
    {
        $client = User::factory()->client()->create();
        $vehicle = Vehicle::factory()->create(['client_id' => $client->id]);
        $order = ServiceOrder::factory()->create([
            'client_id' => $client->id,
            'vehicle_id' => $vehicle->id,
        ]);

        $order->notes = 'atualizado via repository';
        $this->repository->save($order);

        $this->assertDatabaseHas('service_orders', [
            'id' => $order->id,
            'notes' => 'atualizado via repository',
        ]);
    }
}
