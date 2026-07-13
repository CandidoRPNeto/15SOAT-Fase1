<?php

namespace App\Infrastructure\Messaging;

use App\Application\ServiceOrder\ApproveBudget;
use App\Contracts\EmailStatusUpdateServiceInterface;
use App\Models\ServiceOrder;
use InvalidArgumentException;

class StubEmailStatusUpdateService implements EmailStatusUpdateServiceInterface
{
    public function __construct(private readonly ApproveBudget $approveBudget) {}

    public function updateFromEmail(string $orderNumber, string $decision): ServiceOrder
    {
        $decision = strtolower($decision);

        if (! in_array($decision, ['approved', 'rejected'], true)) {
            throw new InvalidArgumentException("Decisão de e-mail inválida: {$decision}. Use approved ou rejected.");
        }

        return $this->approveBudget->execute($orderNumber, $decision === 'approved');
    }
}
