<?php

namespace App\Application\Ports;

use App\Domain\Messaging\MessageEnvelope;

/**
 * Publishes one envelope to the broker. Implementations must throw on
 * failure so the outbox row stays unpublished and is retried.
 */
interface MessagePublisher
{
    public function publish(MessageEnvelope $envelope): void;
}
