<?php

namespace Tests\Unit;

use App\Application\Ports\MessageRouter;
use App\Domain\Messaging\MessageEnvelope;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqConnectionFactory;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqMessageConsumer;
use Closure;
use Tests\Fakes\AcknowledgingAmqpMessage;
use Tests\Fakes\RecordingTraceContext;
use Tests\TestCase;

/** ADR-016: consumer work is traced under the consuming service, not the broker. */
class RabbitMqConsumerTraceTest extends TestCase
{
    public function test_handles_the_message_inside_a_service_span_and_acks(): void
    {
        $trace = new RecordingTraceContext;
        $consumer = new RabbitMqMessageConsumer(new RabbitMqConnectionFactory([]), $this->app->make(MessageRouter::class), $trace);
        $handled = [];
        $this->setHandler($consumer, function (MessageEnvelope $envelope) use ($trace, &$handled) {
            $handled[] = [$envelope->type, $trace->serviceSpans];
        });
        $message = new AcknowledgingAmqpMessage(MessageEnvelope::create('evt.x', 'saga', 'corr', [])->toJson());

        $consumer->onMessage($message);

        $this->assertSame([['evt.x', ['message.handle evt.x']]], $handled);
        $this->assertSame('ack', $message->outcome);
    }

    private function setHandler(RabbitMqMessageConsumer $consumer, callable $handler): void
    {
        // poll() would open a broker connection; only the handler slot is needed.
        Closure::bind(fn () => $this->handler = $handler, $consumer, RabbitMqMessageConsumer::class)();
    }
}
