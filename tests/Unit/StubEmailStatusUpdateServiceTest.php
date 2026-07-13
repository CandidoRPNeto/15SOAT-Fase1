<?php

namespace Tests\Unit;

use App\Application\ServiceOrder\ApproveBudget;
use App\Infrastructure\Messaging\StubEmailStatusUpdateService;
use App\Infrastructure\Persistence\Eloquent\EloquentServiceOrderRepository;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class StubEmailStatusUpdateServiceTest extends TestCase
{
    use RefreshDatabase;

    private StubEmailStatusUpdateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StubEmailStatusUpdateService(
            new ApproveBudget(new EloquentServiceOrderRepository)
        );
    }

    public function test_approves_via_email_simulated_decision(): void
    {
        $client = User::factory()->client()->create();
        $vehicle = Vehicle::factory()->create(['client_id' => $client->id]);
        $order = ServiceOrder::factory()->awaitingApproval()->create([
            'client_id' => $client->id,
            'vehicle_id' => $vehicle->id,
        ]);

        $result = $this->service->updateFromEmail($order->number, 'approved');

        $this->assertSame('approved', $result->status->value);
    }

    public function test_rejects_via_email_simulated_decision(): void
    {
        $client = User::factory()->client()->create();
        $vehicle = Vehicle::factory()->create(['client_id' => $client->id]);
        $order = ServiceOrder::factory()->awaitingApproval()->create([
            'client_id' => $client->id,
            'vehicle_id' => $vehicle->id,
        ]);

        $result = $this->service->updateFromEmail($order->number, 'rejected');

        $this->assertSame('cancelled', $result->status->value);
    }

    public function test_throws_for_unknown_decision(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->updateFromEmail('OS-2026-00001', 'maybe');
    }
}
