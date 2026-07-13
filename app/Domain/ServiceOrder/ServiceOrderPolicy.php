<?php

namespace App\Domain\ServiceOrder;

final class ServiceOrderPolicy
{
    public static function canModifyItems(ServiceOrderStatus $status): bool
    {
        return in_array($status, [ServiceOrderStatus::RECEIVED, ServiceOrderStatus::IN_DIAGNOSIS], true);
    }

    public static function canGenerateBudget(ServiceOrderStatus $status): bool
    {
        return $status === ServiceOrderStatus::IN_DIAGNOSIS;
    }

    public static function canApprove(ServiceOrderStatus $status): bool
    {
        return $status === ServiceOrderStatus::AWAITING_APPROVAL;
    }

    public static function canCancel(ServiceOrderStatus $status): bool
    {
        return $status === ServiceOrderStatus::AWAITING_APPROVAL;
    }

    public static function canStartExecution(ServiceOrderStatus $status): bool
    {
        return $status === ServiceOrderStatus::APPROVED;
    }

    public static function canFinalize(ServiceOrderStatus $status): bool
    {
        return $status === ServiceOrderStatus::IN_EXECUTION;
    }

    public static function canPay(ServiceOrderStatus $status): bool
    {
        return $status === ServiceOrderStatus::FINALIZED;
    }
}
