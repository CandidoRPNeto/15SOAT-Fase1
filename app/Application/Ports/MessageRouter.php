<?php

namespace App\Application\Ports;

use App\Domain\Messaging\MessageEnvelope;

/**
 * Dispatches a consumed envelope to the code that owns its type.
 */
interface MessageRouter
{
    /** @return list<string> routing-key patterns this service consumes */
    public function bindings(): array;

    public function route(MessageEnvelope $envelope): void;
}
