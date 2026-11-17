<?php

namespace Tests\Unit;

use App\Application\Saga\ServiceOrderStatusTransition;
use App\Domain\ServiceOrder\Exceptions\InvalidStatusTransitionException;
use App\Domain\ServiceOrder\ServiceOrderStatus;
use App\Models\ServiceOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceOrderStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_applies_allowed_transition_with_extra_attributes_and_history(): void
    {
        $order = ServiceOrder::factory()->create(['status' => ServiceOrderStatus::IN_EXECUTION]);

        (new ServiceOrderStatusTransition)->apply($order, ServiceOrderStatus::FINALIZED, 'reparo finalizado', ['finalized_at' => now()]);

        $this->assertSame('finalized', $order->refresh()->status->value);
        $this->assertNotNull($order->finalized_at);
        $this->assertDatabaseHas('service_order_status_history', [
            'service_order_id' => $order->id, 'from_status' => 'in_execution', 'to_status' => 'finalized', 'reason' => 'reparo finalizado',
        ]);
    }

    public function test_rejects_disallowed_transition_naming_allowed_targets(): void
    {
        $order = ServiceOrder::factory()->create(['status' => ServiceOrderStatus::RECEIVED]);

        $this->expectException(InvalidStatusTransitionException::class);
        $this->expectExceptionMessage('received -> delivered not allowed; expected one of: in_diagnosis, cancelled');

        (new ServiceOrderStatusTransition)->apply($order, ServiceOrderStatus::DELIVERED, 'x');
    }
}
