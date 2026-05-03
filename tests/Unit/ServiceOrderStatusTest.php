<?php

namespace Tests\Unit;

use App\Enums\ServiceOrderStatus;
use PHPUnit\Framework\TestCase;

class ServiceOrderStatusTest extends TestCase
{
    public function test_received_can_transition_to_in_diagnosis(): void
    {
        $this->assertTrue(ServiceOrderStatus::RECEIVED->canTransitionTo(ServiceOrderStatus::IN_DIAGNOSIS));
    }

    public function test_received_cannot_transition_to_approved(): void
    {
        $this->assertFalse(ServiceOrderStatus::RECEIVED->canTransitionTo(ServiceOrderStatus::APPROVED));
    }

    public function test_in_diagnosis_can_transition_to_awaiting_approval(): void
    {
        $this->assertTrue(ServiceOrderStatus::IN_DIAGNOSIS->canTransitionTo(ServiceOrderStatus::AWAITING_APPROVAL));
    }

    public function test_awaiting_approval_can_transition_to_approved_or_cancelled(): void
    {
        $this->assertTrue(ServiceOrderStatus::AWAITING_APPROVAL->canTransitionTo(ServiceOrderStatus::APPROVED));
        $this->assertTrue(ServiceOrderStatus::AWAITING_APPROVAL->canTransitionTo(ServiceOrderStatus::CANCELLED));
    }

    public function test_cancelled_has_no_transitions(): void
    {
        $this->assertEmpty(ServiceOrderStatus::CANCELLED->allowedTransitions());
    }

    public function test_delivered_has_no_transitions(): void
    {
        $this->assertEmpty(ServiceOrderStatus::DELIVERED->allowedTransitions());
    }

    public function test_finalized_can_transition_to_delivered(): void
    {
        $this->assertTrue(ServiceOrderStatus::FINALIZED->canTransitionTo(ServiceOrderStatus::DELIVERED));
    }

    public function test_label_returns_portuguese_string(): void
    {
        $this->assertSame('Recebida', ServiceOrderStatus::RECEIVED->label());
        $this->assertSame('Em diagnóstico', ServiceOrderStatus::IN_DIAGNOSIS->label());
        $this->assertSame('Aguardando aprovação', ServiceOrderStatus::AWAITING_APPROVAL->label());
        $this->assertSame('Aprovada', ServiceOrderStatus::APPROVED->label());
        $this->assertSame('Cancelada', ServiceOrderStatus::CANCELLED->label());
        $this->assertSame('Em execução', ServiceOrderStatus::IN_EXECUTION->label());
        $this->assertSame('Finalizada', ServiceOrderStatus::FINALIZED->label());
        $this->assertSame('Entregue', ServiceOrderStatus::DELIVERED->label());
    }
}
