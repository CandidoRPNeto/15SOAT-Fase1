<?php

namespace Tests\Unit;

use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use App\Models\ServiceOrderService;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceOrderModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_number_format(): void
    {
        $number = ServiceOrder::generateNumber();
        $this->assertMatchesRegularExpression('/^OS-\d{4}-\d{5}$/', $number);
    }

    public function test_generate_number_increments(): void
    {
        $client = User::factory()->client()->create();
        $vehicle = Vehicle::factory()->create(['client_id' => $client->id]);

        ServiceOrder::factory()->create(['client_id' => $client->id, 'vehicle_id' => $vehicle->id]);
        $second = ServiceOrder::generateNumber();

        $this->assertStringEndsWith('-00002', $second);
    }

    public function test_is_paid_returns_false_when_null(): void
    {
        $order = new ServiceOrder();
        $order->paid_at = null;
        $this->assertFalse($order->isPaid());
    }

    public function test_is_paid_returns_true_when_set(): void
    {
        $client = User::factory()->client()->create();
        $vehicle = Vehicle::factory()->create(['client_id' => $client->id]);
        $order = ServiceOrder::factory()->finalized()->create([
            'client_id' => $client->id,
            'vehicle_id' => $vehicle->id,
            'paid_at' => now(),
        ]);

        $this->assertTrue($order->isPaid());
    }

    public function test_is_finalized(): void
    {
        $order = new ServiceOrder();
        $order->setRawAttributes(['status' => 'finalized']);
        $this->assertTrue($order->isFinalized());

        $order->setRawAttributes(['status' => 'delivered']);
        $this->assertFalse($order->isFinalized());
    }

    public function test_is_deliverable_requires_finalized_and_paid(): void
    {
        $client = User::factory()->client()->create();
        $vehicle = Vehicle::factory()->create(['client_id' => $client->id]);

        $order = ServiceOrder::factory()->finalized()->create([
            'client_id' => $client->id,
            'vehicle_id' => $vehicle->id,
            'paid_at' => null,
        ]);
        $this->assertFalse($order->isDeliverable());

        $order->update(['paid_at' => now()]);
        $this->assertTrue($order->fresh()->isDeliverable());
    }

    public function test_calculate_total_sums_services_and_items(): void
    {
        $svc1 = new ServiceOrderService(['unit_price' => '100.00', 'quantity' => 2]);
        $svc2 = new ServiceOrderService(['unit_price' => '50.00', 'quantity' => 1]);
        $itm1 = new ServiceOrderItem(['unit_price' => '30.00', 'quantity' => 3]);

        $order = new ServiceOrder();
        $order->setRelation('orderServices', new Collection([$svc1, $svc2]));
        $order->setRelation('orderItems', new Collection([$itm1]));

        // 100*2 + 50*1 + 30*3 = 200 + 50 + 90 = 340
        $this->assertEqualsWithDelta(340.0, $order->calculateTotal(), 0.001);
    }

    public function test_hours_since_finalized_returns_null_if_not_finalized(): void
    {
        $order = new ServiceOrder();
        $order->setRawAttributes(['finalized_at' => null]);
        $this->assertNull($order->hoursSinceFinalized());
    }
}
