<?php

namespace Tests\Fakes;

use App\Application\Ports\MessagePublisher;
use App\Domain\Messaging\MessageEnvelope;
use RuntimeException;

/** Records published envelopes; can simulate a broker outage. */
class InMemoryMessagePublisher implements MessagePublisher
{
    /** @var list<MessageEnvelope> */
    public array $published = [];

    public bool $brokerDown = false;

    public function publish(MessageEnvelope $envelope): void
    {
        if ($this->brokerDown) {
            throw new RuntimeException("Broker unavailable while publishing {$envelope->type}");
        }

        $this->published[] = $envelope;
    }

    /** @return list<string> */
    public function publishedTypes(): array
    {
        return array_map(fn (MessageEnvelope $e) => $e->type, $this->published);
    }
}
