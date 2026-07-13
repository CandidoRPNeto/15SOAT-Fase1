<?php

namespace App\Contracts;

use App\Models\ServiceOrder;

interface EmailStatusUpdateServiceInterface
{
    /**
     * Simulates parsing an inbound e-mail with the client's budget decision
     * and applies the same status transition used by the messaging webhook.
     */
    public function updateFromEmail(string $orderNumber, string $decision): ServiceOrder;
}
