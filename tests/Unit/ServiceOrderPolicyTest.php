<?php

namespace Tests\Unit;

use App\Domain\ServiceOrder\ServiceOrderPolicy;
use App\Domain\ServiceOrder\ServiceOrderStatus;
use PHPUnit\Framework\TestCase;

class ServiceOrderPolicyTest extends TestCase
{
    public function test_can_modify_items_only_in_received_or_in_diagnosis(): void
    {
        $this->assertTrue(ServiceOrderPolicy::canModifyItems(ServiceOrderStatus::RECEIVED));
        $this->assertTrue(ServiceOrderPolicy::canModifyItems(ServiceOrderStatus::IN_DIAGNOSIS));
        $this->assertFalse(ServiceOrderPolicy::canModifyItems(ServiceOrderStatus::AWAITING_APPROVAL));
        $this->assertFalse(ServiceOrderPolicy::canModifyItems(ServiceOrderStatus::DELIVERED));
    }
}
