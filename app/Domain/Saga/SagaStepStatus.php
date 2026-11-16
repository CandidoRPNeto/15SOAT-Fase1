<?php

namespace App\Domain\Saga;

enum SagaStepStatus: string
{
    case PENDING = 'pending';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';
    case COMPENSATED = 'compensated';
}
