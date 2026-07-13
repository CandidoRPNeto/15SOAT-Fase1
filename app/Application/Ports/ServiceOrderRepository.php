<?php

namespace App\Application\Ports;

use App\Models\ServiceOrder;

interface ServiceOrderRepository
{
    public function findById(int $id): ?ServiceOrder;

    public function findByNumber(string $number): ?ServiceOrder;

    public function create(array $attributes): ServiceOrder;

    public function save(ServiceOrder $order): ServiceOrder;
}
