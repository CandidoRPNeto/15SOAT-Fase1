<?php

namespace App\Application\Messaging;

use App\Application\Ports\MessagePublisher;
use App\Application\Ports\TraceContext;
use App\Domain\Messaging\MessageEnvelope;
use App\Infrastructure\Tracing\NullTraceContext;
use App\Models\OutboxMessage;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Publishes pending outbox rows in insertion order. A failed publish keeps
 * the row pending (attempts++) and stops the batch to preserve ordering.
 *
 * Example: $published = $relay->relayPending(50);
 */
class OutboxRelay
{
    public function __construct(
        private readonly MessagePublisher $publisher,
        private readonly TraceContext $trace = new NullTraceContext,
    ) {}

    public function relayPending(int $limit): int
    {
        $pending = OutboxMessage::whereNull('published_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $published = 0;

        foreach ($pending as $message) {
            if (! $this->publishOne($message)) {
                break;
            }
            $published++;
        }

        return $published;
    }

    private function publishOne(OutboxMessage $message): bool
    {
        try {
            $this->trace->continueTrace(
                $message->trace_context ?? [],
                'outbox.relay',
                $message->type,
                fn () => $this->publisher->publish(MessageEnvelope::fromArray($message->body)),
            );
        } catch (Throwable $e) {
            $message->increment('attempts');
            Log::warning('outbox.publish_failed', ['message_id' => $message->message_id, 'type' => $message->type, 'error' => $e->getMessage()]);

            return false;
        }

        $message->update(['published_at' => now()]);
        Log::info('outbox.published', ['message_id' => $message->message_id, 'type' => $message->type, 'saga_id' => $message->body['saga_id'] ?? null]);

        return true;
    }
}
