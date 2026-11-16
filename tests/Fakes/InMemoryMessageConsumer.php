<?php

namespace Tests\Fakes;

use App\Application\Ports\MessageConsumer;
use App\Domain\Messaging\MessageEnvelope;
use Throwable;

/** Delivers queued envelopes on poll; mirrors ack / dead-letter semantics. */
class InMemoryMessageConsumer implements MessageConsumer
{
    /** @var list<MessageEnvelope> */
    public array $queue = [];

    /** @var list<MessageEnvelope> */
    public array $deadLettered = [];

    public int $polls = 0;

    public function poll(callable $handler, float $timeoutSeconds): int
    {
        $this->polls++;
        $handled = 0;

        while ($envelope = array_shift($this->queue)) {
            try {
                $handler($envelope);
                $handled++;
            } catch (Throwable) {
                $this->deadLettered[] = $envelope;
            }
        }

        return $handled;
    }
}
