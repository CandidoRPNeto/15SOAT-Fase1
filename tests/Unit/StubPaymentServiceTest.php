<?php

namespace Tests\Unit;

use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\StubPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StubPaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_process_payment_returns_success(): void
    {
        $client = User::factory()->client()->create();
        $vehicle = Vehicle::factory()->create(['client_id' => $client->id]);
        $order = ServiceOrder::factory()->finalized()->create([
            'client_id' => $client->id,
            'vehicle_id' => $vehicle->id,
            'total_amount' => '350.00',
        ]);

        $service = new StubPaymentService();
        $result = $service->processPayment($order);

        $this->assertTrue($result['success']);
        $this->assertStringStartsWith('TXN-', $result['transaction_id']);
        $this->assertStringContainsString('350.00', $result['message']);
    }

    public function test_transaction_id_is_unique(): void
    {
        $client = User::factory()->client()->create();
        $vehicle = Vehicle::factory()->create(['client_id' => $client->id]);
        $order = ServiceOrder::factory()->finalized()->create([
            'client_id' => $client->id,
            'vehicle_id' => $vehicle->id,
            'total_amount' => '100.00',
        ]);

        $service = new StubPaymentService();
        $r1 = $service->processPayment($order);
        $r2 = $service->processPayment($order);

        $this->assertNotSame($r1['transaction_id'], $r2['transaction_id']);
    }
}
