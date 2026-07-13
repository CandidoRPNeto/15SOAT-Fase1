<?php

namespace Tests\Feature;

use App\Domain\ServiceOrder\ServiceOrderStatus;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_returns_open_orders(): void
    {
        $client = User::factory()->client()->create();
        $vehicle = Vehicle::factory()->create(['client_id' => $client->id]);

        ServiceOrder::factory()->count(3)->create([
            'client_id' => $client->id,
            'vehicle_id' => $vehicle->id,
            'status' => ServiceOrderStatus::IN_EXECUTION,
        ]);

        ServiceOrder::factory()->create([
            'client_id' => $client->id,
            'vehicle_id' => $vehicle->id,
            'status' => ServiceOrderStatus::CANCELLED,
        ]);

        ServiceOrder::factory()->delivered()->create([
            'client_id' => $client->id,
            'vehicle_id' => $vehicle->id,
        ]);

        $response = $this->postJson('/webhook/messaging');

        $response->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonStructure([
                'open_orders' => [
                    '*' => ['number', 'status', 'status_label', 'total_amount', 'vehicle_model', 'vehicle_plate'],
                ],
                'total',
            ]);
    }

    public function test_webhook_returns_empty_when_no_open_orders(): void
    {
        $this->postJson('/webhook/messaging')
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_webhook_excludes_cancelled_and_delivered(): void
    {
        $client = User::factory()->client()->create();
        $vehicle = Vehicle::factory()->create(['client_id' => $client->id]);

        ServiceOrder::factory()->create([
            'client_id' => $client->id,
            'vehicle_id' => $vehicle->id,
            'status' => ServiceOrderStatus::CANCELLED,
        ]);
        ServiceOrder::factory()->delivered()->create([
            'client_id' => $client->id,
            'vehicle_id' => $vehicle->id,
        ]);

        $this->postJson('/webhook/messaging')
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_webhook_approves_budget(): void
    {
        $client = User::factory()->client()->create();
        $vehicle = Vehicle::factory()->create(['client_id' => $client->id]);
        $order = ServiceOrder::factory()->awaitingApproval()->create([
            'client_id' => $client->id,
            'vehicle_id' => $vehicle->id,
        ]);

        $this->postJson('/webhook/messaging', [
            'event' => 'budget_approved',
            'order_number' => $order->number,
        ])
            ->assertOk()
            ->assertJsonPath('order.status', 'approved');

        $this->assertDatabaseHas('service_orders', ['id' => $order->id, 'status' => 'approved']);
    }

    public function test_webhook_rejects_budget(): void
    {
        $client = User::factory()->client()->create();
        $vehicle = Vehicle::factory()->create(['client_id' => $client->id]);
        $order = ServiceOrder::factory()->awaitingApproval()->create([
            'client_id' => $client->id,
            'vehicle_id' => $vehicle->id,
        ]);

        $this->postJson('/webhook/messaging', [
            'event' => 'budget_rejected',
            'order_number' => $order->number,
        ])
            ->assertOk()
            ->assertJsonPath('order.status', 'cancelled');

        $this->assertDatabaseHas('service_orders', ['id' => $order->id, 'status' => 'cancelled']);
    }

    public function test_webhook_budget_decision_fails_for_unknown_order(): void
    {
        $this->postJson('/webhook/messaging', [
            'event' => 'budget_approved',
            'order_number' => 'OS-0000-99999',
        ])->assertNotFound();
    }

    public function test_webhook_budget_decision_fails_for_invalid_status(): void
    {
        $client = User::factory()->client()->create();
        $vehicle = Vehicle::factory()->create(['client_id' => $client->id]);
        $order = ServiceOrder::factory()->received()->create([
            'client_id' => $client->id,
            'vehicle_id' => $vehicle->id,
        ]);

        $this->postJson('/webhook/messaging', [
            'event' => 'budget_approved',
            'order_number' => $order->number,
        ])->assertUnprocessable();
    }

    public function test_webhook_rejects_invalid_event(): void
    {
        $this->postJson('/webhook/messaging', [
            'event' => 'something_else',
            'order_number' => 'OS-2026-00001',
        ])->assertUnprocessable();
    }
}
