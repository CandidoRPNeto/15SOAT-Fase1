<?php

namespace Tests\Feature;

use App\Contracts\MessagingServiceInterface;
use App\Contracts\PaymentServiceInterface;
use App\Enums\ServiceOrderStatus;
use App\Models\Item;
use App\Models\Service;
use App\Models\ServiceItem;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
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

    public function test_add_service_auto_creates_required_items(): void
    {
        $order = ServiceOrder::factory()->received()->create([
            'client_id' => $this->client->id,
            'vehicle_id' => $this->vehicle->id,
        ]);
        $service = Service::factory()->create(['price' => 120.00]);
        $item1 = Item::factory()->create(['price' => 35.00]);
        $item2 = Item::factory()->create(['price' => 32.00]);

        ServiceItem::create(['service_id' => $service->id, 'item_id' => $item1->id, 'quantity' => 1]);
        ServiceItem::create(['service_id' => $service->id, 'item_id' => $item2->id, 'quantity' => 4]);

        $this->actingAs($this->mechanic)
            ->postJson("/api/v1/service-orders/{$order->id}/services", [
                'service_id' => $service->id,
                'quantity' => 1,
            ])
            ->assertStatus(201)
            ->assertJsonStructure(['items']);

        $this->assertDatabaseHas('service_order_items', [
            'service_order_id' => $order->id,
            'item_id' => $item1->id,
            'quantity' => 1,
        ]);
        $this->assertDatabaseHas('service_order_items', [
            'service_order_id' => $order->id,
            'item_id' => $item2->id,
            'quantity' => 4,
        ]);
    }

    public function test_add_service_merges_duplicate_items_from_multiple_services(): void
    {
        $order = ServiceOrder::factory()->received()->create([
            'client_id' => $this->client->id,
            'vehicle_id' => $this->vehicle->id,
        ]);
        $item = Item::factory()->create(['price' => 32.00]);
        $service1 = Service::factory()->create();
        $service2 = Service::factory()->create();

        ServiceItem::create(['service_id' => $service1->id, 'item_id' => $item->id, 'quantity' => 2]);
        ServiceItem::create(['service_id' => $service2->id, 'item_id' => $item->id, 'quantity' => 3]);

        $this->actingAs($this->mechanic)
            ->postJson("/api/v1/service-orders/{$order->id}/services", ['service_id' => $service1->id])
            ->assertStatus(201);

        $this->actingAs($this->mechanic)
            ->postJson("/api/v1/service-orders/{$order->id}/services", ['service_id' => $service2->id])
            ->assertStatus(201);

        $this->assertDatabaseHas('service_order_items', [
            'service_order_id' => $order->id,
            'item_id' => $item->id,
            'quantity' => 5,
        ]);
    }

    public function test_mechanic_can_add_item_manually(): void
    {
        $order = ServiceOrder::factory()->received()->create([
            'client_id' => $this->client->id,
            'vehicle_id' => $this->vehicle->id,
        ]);
        $item = Item::factory()->create(['price' => 35.00, 'stock_quantity' => 10]);

        $this->actingAs($this->mechanic)
            ->postJson("/api/v1/service-orders/{$order->id}/items", [
                'item_id' => $item->id,
                'quantity' => 3,
            ])
            ->assertStatus(201)
            ->assertJsonStructure(['items']);

        $this->assertDatabaseHas('service_order_items', [
            'service_order_id' => $order->id,
            'item_id' => $item->id,
            'quantity' => 3,
        ]);
    }

    public function test_mechanic_can_remove_item_from_order(): void
    {
        $order = ServiceOrder::factory()->received()->create([
            'client_id' => $this->client->id,
            'vehicle_id' => $this->vehicle->id,
        ]);
        $item = Item::factory()->create();
        $orderItem = ServiceOrderItem::create([
            'service_order_id' => $order->id,
            'item_id' => $item->id,
            'quantity' => 2,
            'unit_price' => $item->price,
        ]);

        $this->actingAs($this->mechanic)
            ->deleteJson("/api/v1/service-orders/{$order->id}/items/{$orderItem->id}")
            ->assertOk();

        $this->assertDatabaseMissing('service_order_items', ['id' => $orderItem->id]);
    }

    public function test_cannot_add_item_in_invalid_status(): void
    {
        $order = ServiceOrder::factory()->approved()->create([
            'client_id' => $this->client->id,
            'vehicle_id' => $this->vehicle->id,
        ]);
        $item = Item::factory()->create();

        $this->actingAs($this->mechanic)
            ->postJson("/api/v1/service-orders/{$order->id}/items", [
                'item_id' => $item->id,
                'quantity' => 1,
            ])
            ->assertUnprocessable();
    }

    public function test_items_response_includes_requested_and_total_quantity(): void
    {
        $order = ServiceOrder::factory()->received()->create([
            'client_id' => $this->client->id,
            'vehicle_id' => $this->vehicle->id,
        ]);
        $item = Item::factory()->create(['stock_quantity' => 50, 'price' => 35.00]);
        ServiceOrderItem::create([
            'service_order_id' => $order->id,
            'item_id' => $item->id,
            'quantity' => 4,
            'unit_price' => $item->price,
        ]);

        $this->actingAs($this->mechanic)
            ->getJson("/api/v1/service-orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('items.0.requested_quantity', 4)
            ->assertJsonPath('items.0.total_quantity', 50);
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

    public function test_client_can_cancel_order(): void
    {
        $order = ServiceOrder::factory()->awaitingApproval()->create([
            'client_id' => $this->client->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $this->actingAs($this->client)
            ->postJson("/api/v1/service-orders/{$order->id}/cancel")
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');
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
