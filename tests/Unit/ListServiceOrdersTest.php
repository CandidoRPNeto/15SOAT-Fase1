<?php

namespace Tests\Unit;

use App\Application\ServiceOrder\ListServiceOrders;
use App\Domain\ServiceOrder\ServiceOrderStatus;
use App\Models\ServiceOrder;
use Tests\TestCase;

class ListServiceOrdersTest extends TestCase
{
    private function makeOrder(string $status, string $createdAt): ServiceOrder
    {
        $order = new ServiceOrder;
        $order->setRawAttributes([
            'status' => $status,
            'created_at' => $createdAt,
        ]);

        return $order;
    }

    public function test_excluded_statuses_are_finalized_and_delivered(): void
    {
        $this->assertSame(
            [ServiceOrderStatus::FINALIZED, ServiceOrderStatus::DELIVERED],
            ListServiceOrders::excludedStatuses(),
        );
    }

    public function test_sorts_by_status_priority(): void
    {
        $received = $this->makeOrder('received', '2026-01-01 10:00:00');
        $inExecution = $this->makeOrder('in_execution', '2026-01-01 10:00:00');
        $awaitingApproval = $this->makeOrder('awaiting_approval', '2026-01-01 10:00:00');
        $inDiagnosis = $this->makeOrder('in_diagnosis', '2026-01-01 10:00:00');

        $sorted = ListServiceOrders::sort([$received, $inDiagnosis, $awaitingApproval, $inExecution]);

        $this->assertSame(
            ['in_execution', 'awaiting_approval', 'in_diagnosis', 'received'],
            $sorted->map(fn (ServiceOrder $o) => $o->status->value)->all(),
        );
    }

    public function test_sorts_oldest_first_within_the_same_priority(): void
    {
        $newer = $this->makeOrder('received', '2026-01-02 10:00:00');
        $older = $this->makeOrder('received', '2026-01-01 10:00:00');

        $sorted = ListServiceOrders::sort([$newer, $older]);

        $this->assertTrue($sorted->first()->created_at->equalTo($older->created_at));
        $this->assertTrue($sorted->last()->created_at->equalTo($newer->created_at));
    }
}
