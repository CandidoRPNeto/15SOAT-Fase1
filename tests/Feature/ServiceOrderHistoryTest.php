<?php

namespace Tests\Feature;

use App\Domain\Saga\SagaMessage;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SagaDriver;
use Tests\TestCase;

class ServiceOrderHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_lists_status_changes_and_saga_steps(): void
    {
        $driver = new SagaDriver;
        $order = $driver->openOrder();
        $driver->reply($order, SagaMessage::DIAGNOSIS_ENQUEUED);
        $driver->reply($order, SagaMessage::DIAGNOSIS_STARTED);

        $this->actingAs(User::factory()->mechanic()->create())
            ->getJson("/api/v1/service-orders/{$order->id}/history")
            ->assertOk()
            ->assertJsonPath('status', 'in_diagnosis')
            ->assertJsonPath('status_history.0.to', 'received')
            ->assertJsonPath('status_history.1.from', 'received')
            ->assertJsonPath('status_history.1.to', 'in_diagnosis')
            ->assertJsonPath('saga.status', 'running')
            ->assertJsonPath('saga.steps.0.name', 'enqueue_diagnosis')
            ->assertJsonPath('saga.steps.0.status', 'succeeded');
    }

    public function test_order_without_saga_returns_null_saga(): void
    {
        $order = ServiceOrder::factory()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->getJson("/api/v1/service-orders/{$order->id}/history")
            ->assertOk()
            ->assertJsonPath('saga', null)
            ->assertJsonPath('status_history', []);
    }

    public function test_client_cannot_read_another_clients_history(): void
    {
        $order = ServiceOrder::factory()->create();

        $this->actingAs(User::factory()->client()->create())
            ->getJson("/api/v1/service-orders/{$order->id}/history")
            ->assertForbidden();
    }
}
