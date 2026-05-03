<?php

namespace App\Enums;

enum UserRole: string
{
    case RECEPTIONIST = 'receptionist';
    case MECHANIC = 'mechanic';
    case CLIENT = 'client';

    public function label(): string
    {
        return match($this) {
            self::RECEPTIONIST => 'Recepcionista',
            self::MECHANIC => 'Mecânico',
            self::CLIENT => 'Cliente',
        };
    }
}
