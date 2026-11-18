<?php

namespace App\Application\Messaging;

use App\Application\Ports\TraceContext;
use App\Domain\Messaging\MessageEnvelope;
use App\Infrastructure\Tracing\NullTraceContext;
use App\Models\OutboxMessage;

/**
 * Stores an outgoing message in the same DB transaction as the business
 * change (transactional outbox, ADR-011). OutboxRelay publishes it later.
 *
 * Example: DB::transaction(fn () => [$order->save(), $recorder->record($envelope)]);
 */
class OutboxRecorder
{
    public function __construct(private readonly TraceContext $trace = new NullTraceContext) {}

    public function record(MessageEnvelope $envelope): OutboxMessage
    {
        return OutboxMessage::create([
            'message_id' => $envelope->messageId,
            'type' => $envelope->type,
            'body' => $envelope->toArray(),
            // The active span (HTTP request or consumed message) becomes the
            // parent of the publish, keeping the saga in one trace (ADR-016).
            'trace_context' => $this->trace->capture(),
        ]);
    }
}
