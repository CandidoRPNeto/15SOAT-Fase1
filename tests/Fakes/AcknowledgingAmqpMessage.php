<?php

namespace Tests\Fakes;

use PhpAmqpLib\Message\AMQPMessage;

/** AMQPMessage without a broker channel: records ack/nack instead of sending them. */
final class AcknowledgingAmqpMessage extends AMQPMessage
{
    public ?string $outcome = null;

    public function ack($multiple = false): void
    {
        $this->outcome = 'ack';
    }

    public function nack($requeue = false, $multiple = false): void
    {
        $this->outcome = 'nack';
    }
}
