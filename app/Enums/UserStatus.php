<?php

namespace App\Enums;

/**
 * Fase 3 (evolucao_fase3): a Function Serverless de auth por CPF precisa
 * "consultar a existência e o status do cliente" — não existia coluna de
 * status até aqui, só `role`. Ver RFC-003
 * (docs/architecture/rfcs/rfc-003-cpf-auth-strategy.md).
 */
enum UserStatus: string
{
    case ACTIVE = 'active';
    case BLOCKED = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Ativo',
            self::BLOCKED => 'Bloqueado',
        };
    }
}
