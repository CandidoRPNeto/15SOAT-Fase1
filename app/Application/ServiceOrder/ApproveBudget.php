<?php

namespace App\Application\ServiceOrder;

use App\Application\Ports\ServiceOrderRepository;
use App\Domain\ServiceOrder\Exceptions\InvalidStatusTransitionException;
use App\Domain\ServiceOrder\ServiceOrderPolicy;
use App\Domain\ServiceOrder\ServiceOrderStatus;
use App\Models\ServiceOrder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ApproveBudget
{
    public function __construct(private readonly ServiceOrderRepository $repository) {}

    public function execute(string $orderNumber, bool $approved): ServiceOrder
    {
        $order = $this->repository->findByNumber($orderNumber);

        if ($order === null) {
            throw (new ModelNotFoundException)->setModel(ServiceOrder::class, [$orderNumber]);
        }

        if ($approved) {
            if (! ServiceOrderPolicy::canApprove($order->status)) {
                throw new InvalidStatusTransitionException(
                    "A OS {$orderNumber} deve estar Aguardando aprovação para ser aprovada."
                );
            }
            $order->status = ServiceOrderStatus::APPROVED;
        } else {
            if (! ServiceOrderPolicy::canCancel($order->status)) {
                throw new InvalidStatusTransitionException(
                    "A OS {$orderNumber} deve estar Aguardando aprovação para ser cancelada."
                );
            }
            $order->status = ServiceOrderStatus::CANCELLED;
        }

        return $this->repository->save($order);
    }
}
