<?php

namespace App\Enums;

enum ItemType: string
{
    case SUPPLY = 'insumo';
    case PART = 'peca';

    public function label(): string
    {
        return match ($this) {
            self::SUPPLY => 'Insumo',
            self::PART => 'Peça',
        };
    }
}
