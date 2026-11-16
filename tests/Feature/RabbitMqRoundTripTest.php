<?php

namespace Tests\Feature;

use App\Application\Ports\MessageRouter;
use App\Domain\Messaging\MessageEnvelope;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqConnectionFactory;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqMessageConsumer;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqMessagePublisher;
use RuntimeException;
use Tests\TestCase;

/**
 * Integration test against a real broker. Runs when RABBITMQ_TEST_HOST is
 * set (CI service container, or `docker run -p 5672:5672 rabbitmq:3`).
 */
class RabbitMqRoundTripTest extends TestCase
{
    private RabbitMqConnectionFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $host = getenv('RABBITMQ_TEST_HOST');
        if ($host === false || $host === '') {
            $this->markTestSkipped('RABBITMQ_TEST_HOST not set; broker integration test skipped.');
        }

        $this->factory = new RabbitMqConnectionFactory([
            'host' => $host, 'port' => (int) (getenv('RABBITMQ_TEST_PORT') ?: 5672), 'user' => 'guest', 'password' => 'guest', 'vhost' => '/',
            'exchange' => 'workshop.test', 'dead_letter_exchange' => 'workshop.test.dlx',
            'queue' => 'os.test.'.uniqid(), 'prefetch' => 5,
        ]);
    }

    private function router(): MessageRouter
    {
        return new class implements MessageRouter
        {
            public function bindings(): array
            {
                return ['evt.test.*'];
            }

            public function route(MessageEnvelope $envelope): void {}
        };
    }

    public function test_published_message_reaches_the_bound_queue_and_is_acked(): void
    {
        $consumer = new RabbitMqMessageConsumer($this->factory, $this->router());
        $consumer->poll(fn () => null, 0.2); // declares queue + bindings

        $sent = MessageEnvelope::create('evt.test.ping', 'saga-1', 'corr-1', ['n' => 1]);
        (new RabbitMqMessagePublisher($this->factory))->publish($sent);

        $received = [];
        $consumer->poll(function (MessageEnvelope $e) use (&$received) {
            $received[] = $e;
        }, 2.0);

        $this->assertCount(1, $received);
        $this->assertEquals($sent, $received[0]);
    }

    public function test_failing_handler_dead_letters_instead_of_requeueing(): void
    {
        $consumer = new RabbitMqMessageConsumer($this->factory, $this->router());
        $consumer->poll(fn () => null, 0.2);
        (new RabbitMqMessagePublisher($this->factory))->publish(MessageEnvelope::create('evt.test.boom', 's', 'c', []));

        $handled = $consumer->poll(fn () => throw new RuntimeException('boom'), 2.0);
        $again = $consumer->poll(fn () => null, 0.5);

        $this->assertSame(0, $handled);
        $this->assertSame(0, $again, 'nacked message must go to the DLQ, not back to the queue');
    }
}
