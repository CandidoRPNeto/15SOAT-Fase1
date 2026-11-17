<?php

namespace App\Infrastructure\Messaging\RabbitMq;

use App\Application\Ports\MessageConsumer;
use App\Application\Ports\MessageRouter;
use App\Domain\Messaging\MessageEnvelope;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

/**
 * Consumes this service's queue (bindings come from the MessageRouter).
 * Ack after the handler commits; a throwing handler sends the message to the DLQ.
 */
class RabbitMqMessageConsumer implements MessageConsumer
{
    private ?AMQPStreamConnection $connection = null;

    private ?AMQPChannel $channel = null;

    /** @var callable(MessageEnvelope): mixed|null */
    private $handler = null;

    private int $handled = 0;

    public function __construct(
        private readonly RabbitMqConnectionFactory $factory,
        private readonly MessageRouter $router,
    ) {}

    public function poll(callable $handler, float $timeoutSeconds): int
    {
        $this->handler = $handler;
        $this->handled = 0;

        try {
            $this->channel()->wait(null, false, $timeoutSeconds);
        } catch (AMQPTimeoutException) {
            // No message within the timeout: normal idle poll.
        }

        return $this->handled;
    }

    public function onMessage(AMQPMessage $message): void
    {
        try {
            ($this->handler)(MessageEnvelope::fromJson($message->getBody()));
            $message->ack();
            $this->handled++;
        } catch (Throwable $e) {
            Log::error('inbox.dead_lettered', ['body' => $message->getBody(), 'error' => $e->getMessage()]);
            $message->nack(false);
        }
    }

    private function channel(): AMQPChannel
    {
        if ($this->channel !== null && $this->channel->is_open()) {
            return $this->channel;
        }

        $this->connection = $this->factory->connect();
        $this->channel = $this->connection->channel();
        $this->factory->declareExchanges($this->channel);
        $queue = $this->factory->declareQueue($this->channel, $this->router->bindings());
        $this->channel->basic_consume($queue, '', false, false, false, false, [$this, 'onMessage']);

        return $this->channel;
    }

    public function __destruct()
    {
        $this->channel?->close();
        $this->connection?->close();
    }
}
