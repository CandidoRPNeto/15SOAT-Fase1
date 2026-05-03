<?php

namespace App\Enums;

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
        return match($this) {
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
        return match($this) {
            self::RECEIVED => [self::IN_DIAGNOSIS],
            self::IN_DIAGNOSIS => [self::AWAITING_APPROVAL],
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
