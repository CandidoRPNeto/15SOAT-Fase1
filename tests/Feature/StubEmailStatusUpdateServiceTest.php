<?php

namespace Tests\Feature;

use App\Contracts\EmailStatusUpdateServiceInterface;
use App\Infrastructure\Messaging\StubEmailStatusUpdateService;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StubEmailStatusUpdateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_container_resolves_the_stub_implementation(): void
    {
        $this->assertInstanceOf(
            StubEmailStatusUpdateService::class,
            app(EmailStatusUpdateServiceInterface::class)
        );
    }

    public function test_updates_order_status_reusing_the_webhook_transition(): void
    {
        $client = User::factory()->client()->create();
        $vehicle = Vehicle::factory()->create(['client_id' => $client->id]);
        $order = ServiceOrder::factory()->awaitingApproval()->create([
            'client_id' => $client->id,
            'vehicle_id' => $vehicle->id,
        ]);

        $service = app(EmailStatusUpdateServiceInterface::class);
        $result = $service->updateFromEmail($order->number, 'approved');

        $this->assertSame('approved', $result->status->value);
        $this->assertDatabaseHas('service_orders', ['id' => $order->id, 'status' => 'approved']);
    }
}
