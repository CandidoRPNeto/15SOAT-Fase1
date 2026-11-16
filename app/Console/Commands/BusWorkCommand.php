<?php

namespace App\Console\Commands;

use App\Application\Messaging\InboxProcessor;
use App\Application\Messaging\OutboxRelay;
use App\Application\Ports\MessageConsumer;
use App\Domain\Messaging\MessageEnvelope;
use Illuminate\Console\Command;

/**
 * Worker loop for the `os-worker` Deployment: publish pending outbox rows,
 * then consume replies for up to --poll seconds, repeat.
 *
 * Example: php artisan bus:work --poll=1
 */
class BusWorkCommand extends Command
{
    protected $signature = 'bus:work {--poll=1 : seconds to wait for messages per iteration} {--iterations=0 : stop after N iterations (0 = forever)}';

    protected $description = 'Relay the outbox to RabbitMQ and consume saga replies';

    public function handle(OutboxRelay $relay, MessageConsumer $consumer, InboxProcessor $inbox): int
    {
        $limit = (int) config('messaging.relay_batch_size');
        $poll = (float) $this->option('poll');
        $iterations = (int) $this->option('iterations');

        for ($i = 1; $iterations === 0 || $i <= $iterations; $i++) {
            $relay->relayPending($limit);
            $consumer->poll(fn (MessageEnvelope $envelope) => $inbox->process($envelope), $poll);
        }

        return self::SUCCESS;
    }
}
