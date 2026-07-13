<?php

namespace App\Application\ServiceOrder;

use App\Application\Ports\ServiceOrderRepository;
use App\Models\ServiceOrder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class GetServiceOrderStatus
{
    public function __construct(private readonly ServiceOrderRepository $repository) {}

    public function execute(int $id): ServiceOrder
    {
        $order = $this->repository->findById($id);

        if ($order === null) {
            throw (new ModelNotFoundException)->setModel(ServiceOrder::class, [$id]);
        }

        return $order;
    }
}
