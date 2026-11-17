<?php

namespace App\Infrastructure\Messaging\RabbitMq;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Wire\AMQPTable;

/**
 * Opens the connection and declares the topology every service shares:
 * topic exchange, dead-letter exchange, this service's queue + its DLQ.
 * Integration-only (needs a broker); covered by the kind end-to-end run.
 */
class RabbitMqConnectionFactory
{
    /** @param array{host: string, port: int, user: string, password: string, vhost: string, exchange: string, dead_letter_exchange: string, queue: string, prefetch: int} $config */
    public function __construct(private readonly array $config) {}

    public function connect(): AMQPStreamConnection
    {
        return new AMQPStreamConnection(
            $this->config['host'], $this->config['port'], $this->config['user'], $this->config['password'], $this->config['vhost'],
        );
    }

    public function declareExchanges(AMQPChannel $channel): void
    {
        $channel->exchange_declare($this->config['exchange'], 'topic', false, true, false);
        $channel->exchange_declare($this->config['dead_letter_exchange'], 'topic', false, true, false);
    }

    /** @param list<string> $bindings */
    public function declareQueue(AMQPChannel $channel, array $bindings): string
    {
        $queue = $this->config['queue'];
        $dlq = "{$queue}.dlq";

        $channel->queue_declare($dlq, false, true, false, false);
        $channel->queue_bind($dlq, $this->config['dead_letter_exchange'], '#');
        $channel->queue_declare($queue, false, true, false, false, false, new AMQPTable([
            'x-dead-letter-exchange' => $this->config['dead_letter_exchange'],
        ]));

        foreach ($bindings as $pattern) {
            $channel->queue_bind($queue, $this->config['exchange'], $pattern);
        }
        $channel->basic_qos(0, $this->config['prefetch'], false);

        return $queue;
    }

    public function exchange(): string
    {
        return $this->config['exchange'];
    }
}
