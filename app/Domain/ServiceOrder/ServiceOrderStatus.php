<?php

namespace App\Domain\ServiceOrder;

enum ServiceOrderStatus: string
{
    case RECEIVED = 'received';
    case IN_DIAGNOSIS = 'in_diagnosis';
    case AWAITING_APPROVAL = 'awaiting_approval';
    case APPROVED = 'approved';
    case CANCELLED = 'cancelled';
    case IN_EXECUTION = 'in_execution';
    case FINALIZED = 'finalized';
    case DELIVERED = 'delivered';

    public function label(): string
    {
        return match ($this) {
            self::RECEIVED => 'Recebida',
            self::IN_DIAGNOSIS => 'Em diagnóstico',
            self::AWAITING_APPROVAL => 'Aguardando aprovação',
            self::APPROVED => 'Aprovada',
            self::CANCELLED => 'Cancelada',
            self::IN_EXECUTION => 'Em execução',
            self::FINALIZED => 'Finalizada',
            self::DELIVERED => 'Entregue',
        };
    }

    public function allowedTransitions(): array
    {
        return match ($this) {
            // CANCELLED from RECEIVED/IN_DIAGNOSIS: saga compensation when the
            // diagnosis cannot be queued or the budget cannot be created (ADR-010).
            self::RECEIVED => [self::IN_DIAGNOSIS, self::CANCELLED],
            self::IN_DIAGNOSIS => [self::AWAITING_APPROVAL, self::CANCELLED],
            self::AWAITING_APPROVAL => [self::APPROVED, self::CANCELLED],
            self::APPROVED => [self::IN_EXECUTION],
            self::IN_EXECUTION => [self::FINALIZED],
            self::FINALIZED => [self::DELIVERED],
            self::CANCELLED, self::DELIVERED => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions());
    }
}
