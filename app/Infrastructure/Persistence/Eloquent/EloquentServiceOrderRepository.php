<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Ports\ServiceOrderRepository;
use App\Models\ServiceOrder;

class EloquentServiceOrderRepository implements ServiceOrderRepository
{
    public function findById(int $id): ?ServiceOrder
    {
        return ServiceOrder::find($id);
    }

    public function findByNumber(string $number): ?ServiceOrder
    {
        return ServiceOrder::where('number', $number)->first();
    }

    public function create(array $attributes): ServiceOrder
    {
        return ServiceOrder::create($attributes);
    }

    public function save(ServiceOrder $order): ServiceOrder
    {
        $order->save();

        return $order;
    }
}
