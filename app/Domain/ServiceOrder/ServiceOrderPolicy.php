<?php

namespace App\Domain\ServiceOrder;

/**
 * Fase 4: approval/payment/execution rules moved to Billing and Execution;
 * status changes go through ServiceOrderStatusTransition.
 */
final class ServiceOrderPolicy
{
    public static function canModifyItems(ServiceOrderStatus $status): bool
    {
        return in_array($status, [ServiceOrderStatus::RECEIVED, ServiceOrderStatus::IN_DIAGNOSIS], true);
    }
}
