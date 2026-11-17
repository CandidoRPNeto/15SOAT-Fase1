<?php

namespace App\Application\Messaging;

use App\Application\Ports\MessageRouter;
use App\Domain\Messaging\MessageEnvelope;
use App\Models\ProcessedMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Idempotent consumer: handles each message_id at most once. The handler and
 * the processed marker share one DB transaction, so a crash re-delivers
 * the message instead of losing or duplicating its effect.
 *
 * Example: $processor->process(MessageEnvelope::fromJson($body)); // false when duplicate
 */
class InboxProcessor
{
    public function __construct(private readonly MessageRouter $router) {}

    public function process(MessageEnvelope $envelope): bool
    {
        Log::withContext(['correlation_id' => $envelope->correlationId, 'saga_id' => $envelope->sagaId]);

        return DB::transaction(function () use ($envelope): bool {
            if (ProcessedMessage::where('message_id', $envelope->messageId)->exists()) {
                Log::info('inbox.duplicate_skipped', ['message_id' => $envelope->messageId, 'type' => $envelope->type]);

                return false;
            }

            $this->router->route($envelope);
            ProcessedMessage::create([
                'message_id' => $envelope->messageId,
                'type' => $envelope->type,
                'processed_at' => now(),
            ]);
            Log::info('inbox.processed', ['message_id' => $envelope->messageId, 'type' => $envelope->type]);

            return true;
        });
    }
}
