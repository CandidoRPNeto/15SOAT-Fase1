<?php

namespace App\Infrastructure\Messaging\RabbitMq;

use App\Application\Ports\MessagePublisher;
use App\Domain\Messaging\MessageEnvelope;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Persistent messages with publisher confirms: publish() returns only after
 * the broker acked, so OutboxRelay marks a row published only when it is safe.
 */
class RabbitMqMessagePublisher implements MessagePublisher
{
    private ?AMQPStreamConnection $connection = null;

    private ?AMQPChannel $channel = null;

    public function __construct(private readonly RabbitMqConnectionFactory $factory) {}

    public function publish(MessageEnvelope $envelope): void
    {
        $channel = $this->channel();
        $message = new AMQPMessage($envelope->toJson(), [
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'message_id' => $envelope->messageId,
            'correlation_id' => $envelope->correlationId,
            'type' => $envelope->type,
        ]);

        $channel->basic_publish($message, $this->factory->exchange(), $envelope->type);
        $channel->wait_for_pending_acks(5.0);
    }

    private function channel(): AMQPChannel
    {
        if ($this->channel !== null && $this->channel->is_open()) {
            return $this->channel;
        }

        $this->connection = $this->factory->connect();
        $this->channel = $this->connection->channel();
        $this->channel->confirm_select();
        $this->factory->declareExchanges($this->channel);

        return $this->channel;
    }

    public function __destruct()
    {
        $this->channel?->close();
        $this->connection?->close();
    }
}
