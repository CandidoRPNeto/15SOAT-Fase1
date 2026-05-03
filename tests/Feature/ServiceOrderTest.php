<?php

namespace Tests\Feature;

use App\Contracts\MessagingServiceInterface;
use App\Contracts\PaymentServiceInterface;
use App\Enums\ServiceOrderStatus;
use App\Models\Part;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $mechanic;
    private User $receptionist;
    private User $client;
    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mechanic = User::factory()->mechanic()->create();
        $this->receptionist = User::factory()->receptionist()->create();
        $this->client = User::factory()->client()->create();
        $this->vehicle = Vehicle::factory()->create(['client_id' => $this->client->id]);

        $this->mock(MessagingServiceInterface::class, function ($mock) {
            $mock->shouldReceive('notifyOrderCreated')->andReturn(['success' => true, 'message_id' => 'MSG-1', 'message' => 'sent']);
            $mock->shouldReceive('notifyBudgetReady')->andReturn(['success' => true, 'message_id' => 'MSG-2', 'message' => 'sent']);
            $mock->shouldReceive('notifyPickupReady')->andReturn(['success' => true, 'message_id' => 'MSG-3', 'message' => 'sent']);
        });
    }

    public function test_mechanic_can_create_service_order(): void
    {
        $response = $this->actingAs($this->mechanic)
            ->postJson('/api/v1/service-orders', [
                'client_id' => $this->client->id,
                'vehicle_id' => $this->vehicle->id,
                'notes' => 'Veículo com barulho no motor',
            ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'received')
            ->assertJsonStructure(['number', 'status', 'client', 'vehicle']);

        $this->assertDatabaseHas('service_orders', ['client_id' => $this->client->id]);
    }

    public function test_client_cannot_create_service_order(): void
    {
        $this->actingAs($this->client)
            ->postJson('/api/v1/service-orders', [
                'client_id' => $this->client->id,
                'vehicle_id' => $this->vehicle->id,
            ])
            ->assertForbidden();
    }

    public function test_mechanic_can_add_service_to_order(): void
    {
        $order = ServiceOrder::factory()->received()->create([
            'client_id' => $this->client->id,
            'vehicle_id' => $this->vehicle->id,
        ]);
        $service = Service::factory()->create(['price' => 120.00]);

        $this->actingAs($this->mechanic)
            ->postJson("/api/v1/service-orders/{$order->id}/services", [
                'service_id' => $service->id,
                'quantity' => 1,
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('service_order_services', [
            'service_order_id' => $order->id,
            'service_id' => $service->id,
        ]);
    }

    public function test_mechanic_can_add_part_and_stock_decrements(): void
    {
        $order = ServiceOrder::factory()->received()->create([
            'client_id' => $this->client->id,
            'vehicle_id' => $this->vehicle->id,
        ]);
        $part = Part::factory()->create(['price' => 35.00, 'stock_quantity' => 10]);

        $this->actingAs($this->mechanic)
            ->postJson("/api/v1/service-orders/{$order->id}/parts", [
                'part_id' => $part->id,
                'quantity' => 3,
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('parts', ['id' => $part->id, 'stock_quantity' => 7]);
    }

    public function test_cannot_add_part_with_insufficient_stock(): void
    {
        $order = ServiceOrder::factory()->received()->create([
            'client_id' => $this->client->id,
            'vehicle_id' => $this->vehicle->id,
        ]);
        $part = Part::factory()->outOfStock()->create();

        $this->actingAs($this->mechanic)
            ->postJson("/api/v1/service-orders/{$order->id}/parts", [
                'part_id' => $part->id,
                'quantity' => 1,
            ])
            ->assertUnprocessable();
    }

    public function test_full_flow_generate_budget_advances_status(): void
    {
        $order = ServiceOrder::factory()->create([
            'client_id' => $this->client->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => ServiceOrderStatus::IN_DIAGNOSIS,
        ]);

        Service::factory()->create(['price' => 200.00]);
        $service = Service::first();
        $order->orderServices()->create([
            'service_id' => $service->id,
            'quantity' => 1,
            'unit_price' => $service->price,
        ]);

        $this->actingAs($this->mechanic)
            ->postJson("/api/v1/service-orders/{$order->id}/generate-budget")
            ->assertOk()
            ->assertJsonPath('status', 'awaiting_approval')
            ->assertJsonPath('total_amount', '200.00');
    }

    public function test_generate_budget_fails_if_not_in_diagnosis(): void
    {
        $order = ServiceOrder::factory()->received()->create([
            'client_id' => $this->client->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $this->actingAs($this->mechanic)
            ->postJson("/api/v1/service-orders/{$order->id}/generate-budget")
            ->assertUnprocessable();
    }

    public function test_client_can_approve_order(): void
    {
        $order = ServiceOrder::factory()->awaitingApproval()->create([
            'client_id' => $this->client->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $this->actingAs($this->client)
            ->postJson("/api/v1/service-orders/{$order->id}/approve")
            ->assertOk()
            ->assertJsonPath('status', 'approved');
    }

    public function test_client_can_cancel_order_and_stock_is_restored(): void
    {
        $part = Part::factory()->create(['stock_quantity' => 5]);
        $order = ServiceOrder::factory()->awaitingApproval()->create([
            'client_id' => $this->client->id,
            'vehicle_id' => $this->vehicle->id,
        ]);
        $order->orderParts()->create([
            'part_id' => $part->id,
            'quantity' => 2,
            'unit_price' => $part->price,
        ]);

        $this->actingAs($this->client)
            ->postJson("/api/v1/service-orders/{$order->id}/cancel")
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');

        $this->assertDatabaseHas('parts', ['id' => $part->id, 'stock_quantity' => 7]);
    }

    public function test_other_client_cannot_approve_order(): void
    {
        $otherClient = User::factory()->client()->create();
        $order = ServiceOrder::factory()->awaitingApproval()->create([
            'client_id' => $this->client->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $this->actingAs($otherClient)
            ->postJson("/api/v1/service-orders/{$order->id}/approve")
            ->assertForbidden();
    }

    public function test_mechanic_can_start_execution(): void
    {
        $order = ServiceOrder::factory()->approved()->create([
            'client_id' => $this->client->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $this->actingAs($this->mechanic)
            ->postJson("/api/v1/service-orders/{$order->id}/start-execution")
            ->assertOk()
            ->assertJsonPath('status', 'in_execution');
    }

    public function test_mechanic_can_finalize_order(): void
    {
        $order = ServiceOrder::factory()->create([
            'client_id' => $this->client->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => ServiceOrderStatus::IN_EXECUTION,
        ]);

        $this->actingAs($this->mechanic)
            ->postJson("/api/v1/service-orders/{$order->id}/finalize")
            ->assertOk()
            ->assertJsonPath('status', 'finalized');

        $this->assertDatabaseHas('service_orders', [
            'id' => $order->id,
            'status' => 'finalized',
        ]);
    }

    public function test_client_can_pay_finalized_order(): void
    {
        $this->mock(PaymentServiceInterface::class, function ($mock) {
            $mock->shouldReceive('processPayment')->andReturn([
                'success' => true,
                'transaction_id' => 'TXN-MOCK123',
                'message' => 'Pagamento processado',
            ]);
        });

        $order = ServiceOrder::factory()->finalized()->create([
            'client_id' => $this->client->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $this->actingAs($this->client)
            ->postJson("/api/v1/service-orders/{$order->id}/pay")
            ->assertOk()
            ->assertJsonPath('transaction_id', 'TXN-MOCK123');

        $this->assertDatabaseHas('service_orders', ['id' => $order->id]);
        $this->assertNotNull(ServiceOrder::find($order->id)->paid_at);
    }

    public function test_receptionist_can_deliver_paid_order(): void
    {
        $order = ServiceOrder::factory()->finalized()->create([
            'client_id' => $this->client->id,
            'vehicle_id' => $this->vehicle->id,
            'paid_at' => now(),
        ]);

        $this->actingAs($this->receptionist)
            ->postJson("/api/v1/service-orders/{$order->id}/deliver")
            ->assertOk()
            ->assertJsonPath('status', 'delivered');
    }

    public function test_receptionist_cannot_deliver_unpaid_order(): void
    {
        $order = ServiceOrder::factory()->finalized()->create([
            'client_id' => $this->client->id,
            'vehicle_id' => $this->vehicle->id,
            'paid_at' => null,
        ]);

        $this->actingAs($this->receptionist)
            ->postJson("/api/v1/service-orders/{$order->id}/deliver")
            ->assertUnprocessable();
    }

    public function test_client_sees_only_own_orders(): void
    {
        ServiceOrder::factory()->count(2)->create([
            'client_id' => $this->client->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $otherClient = User::factory()->client()->create();
        $otherVehicle = Vehicle::factory()->create(['client_id' => $otherClient->id]);
        ServiceOrder::factory()->count(3)->create([
            'client_id' => $otherClient->id,
            'vehicle_id' => $otherVehicle->id,
        ]);

        $this->actingAs($this->client)
            ->getJson('/api/v1/service-orders')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_mechanic_sees_all_orders(): void
    {
        ServiceOrder::factory()->count(5)->create([
            'client_id' => $this->client->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $this->actingAs($this->mechanic)
            ->getJson('/api/v1/service-orders')
            ->assertOk()
            ->assertJsonPath('meta.total', 5);
    }
}
