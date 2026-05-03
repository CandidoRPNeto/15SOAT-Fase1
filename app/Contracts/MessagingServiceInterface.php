<?php

namespace App\Contracts;

use App\Models\ServiceOrder;

interface MessagingServiceInterface
{
    /**
     * Send message to the client of a service order.
     *
     * @return array{success: bool, message_id: string, message: string}
     */
    public function send(ServiceOrder $order, string $message): array;

    public function notifyOrderCreated(ServiceOrder $order): array;

    public function notifyBudgetReady(ServiceOrder $order): array;

    public function notifyPickupReady(ServiceOrder $order): array;

    public function notifyPickupOverdue(ServiceOrder $order): array;
}
