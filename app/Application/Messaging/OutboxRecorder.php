<?php

namespace App\Application\Messaging;

use App\Domain\Messaging\MessageEnvelope;
use App\Models\OutboxMessage;

/**
 * Stores an outgoing message in the same DB transaction as the business
 * change (transactional outbox, ADR-011). OutboxRelay publishes it later.
 *
 * Example: DB::transaction(fn () => [$order->save(), $recorder->record($envelope)]);
 */
class OutboxRecorder
{
    public function record(MessageEnvelope $envelope): OutboxMessage
    {
        return OutboxMessage::create([
            'message_id' => $envelope->messageId,
            'type' => $envelope->type,
            'body' => $envelope->toArray(),
        ]);
    }
}
