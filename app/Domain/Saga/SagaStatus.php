<?php

namespace App\Domain\Saga;

enum SagaStatus: string
{
    case RUNNING = 'running';
    case COMPENSATED = 'compensated';
    case COMPLETED = 'completed';

    public function isFinished(): bool
    {
        return $this !== self::RUNNING;
    }
}
