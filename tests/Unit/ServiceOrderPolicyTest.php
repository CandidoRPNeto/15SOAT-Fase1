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

    public function test_can_generate_budget_only_in_diagnosis(): void
    {
        $this->assertTrue(ServiceOrderPolicy::canGenerateBudget(ServiceOrderStatus::IN_DIAGNOSIS));
        $this->assertFalse(ServiceOrderPolicy::canGenerateBudget(ServiceOrderStatus::RECEIVED));
    }

    public function test_can_approve_only_awaiting_approval(): void
    {
        $this->assertTrue(ServiceOrderPolicy::canApprove(ServiceOrderStatus::AWAITING_APPROVAL));
        $this->assertFalse(ServiceOrderPolicy::canApprove(ServiceOrderStatus::APPROVED));
    }

    public function test_can_cancel_only_awaiting_approval(): void
    {
        $this->assertTrue(ServiceOrderPolicy::canCancel(ServiceOrderStatus::AWAITING_APPROVAL));
        $this->assertFalse(ServiceOrderPolicy::canCancel(ServiceOrderStatus::IN_EXECUTION));
    }

    public function test_can_start_execution_only_approved(): void
    {
        $this->assertTrue(ServiceOrderPolicy::canStartExecution(ServiceOrderStatus::APPROVED));
        $this->assertFalse(ServiceOrderPolicy::canStartExecution(ServiceOrderStatus::IN_EXECUTION));
    }

    public function test_can_finalize_only_in_execution(): void
    {
        $this->assertTrue(ServiceOrderPolicy::canFinalize(ServiceOrderStatus::IN_EXECUTION));
        $this->assertFalse(ServiceOrderPolicy::canFinalize(ServiceOrderStatus::FINALIZED));
    }

    public function test_can_pay_only_finalized(): void
    {
        $this->assertTrue(ServiceOrderPolicy::canPay(ServiceOrderStatus::FINALIZED));
        $this->assertFalse(ServiceOrderPolicy::canPay(ServiceOrderStatus::DELIVERED));
    }
}
