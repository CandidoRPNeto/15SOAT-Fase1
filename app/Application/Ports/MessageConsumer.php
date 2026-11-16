<?php

namespace App\Application\Ports;

use App\Domain\Messaging\MessageEnvelope;

/**
 * Pulls messages from this service's queue. The handler receives a
 * MessageEnvelope; the consumer acks on return and dead-letters on throw.
 */
interface MessageConsumer
{
    /**
     * @param  callable(MessageEnvelope): mixed  $handler
     * @return int messages handled in this poll
     */
    public function poll(callable $handler, float $timeoutSeconds): int;
}
