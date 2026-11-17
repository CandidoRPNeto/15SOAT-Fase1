<?php

namespace App\Domain\Saga;

/**
 * Saga steps (ADR-010). Compensable steps are undone on failure; after the
 * pivot (repair started) only retries apply.
 */
enum SagaStepName: string
{
    case ENQUEUE_DIAGNOSIS = 'enqueue_diagnosis';
    case CREATE_BUDGET = 'create_budget';
    case RESERVE_STOCK = 'reserve_stock';
    case ENQUEUE_REPAIR = 'enqueue_repair';
    case REQUEST_PAYMENT = 'request_payment';

    public function isCompensable(): bool
    {
        return $this !== self::REQUEST_PAYMENT;
    }
}
