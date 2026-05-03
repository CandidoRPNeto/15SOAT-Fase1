<?php

namespace App\Contracts;

use App\Models\ServiceOrder;

interface PaymentServiceInterface
{
    /**
     * Process payment for a service order.
     *
     * @return array{success: bool, transaction_id: string, message: string}
     */
    public function processPayment(ServiceOrder $order): array;
}
