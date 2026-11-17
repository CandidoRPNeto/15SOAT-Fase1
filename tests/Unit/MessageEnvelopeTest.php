<?php

namespace Tests\Unit;

use App\Domain\Messaging\MessageEnvelope;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MessageEnvelopeTest extends TestCase
{
    public function test_create_fills_ids_and_timestamp(): void
    {
        $envelope = MessageEnvelope::create('cmd.billing.create_budget', 'saga-1', 'corr-1', ['total' => 10.5]);

        $this->assertNotEmpty($envelope->messageId);
        $this->assertSame('saga-1', $envelope->sagaId);
        $this->assertSame('corr-1', $envelope->correlationId);
        $this->assertNotEmpty($envelope->occurredAt);
    }

    public function test_json_round_trip_preserves_every_field(): void
    {
        $original = MessageEnvelope::create('evt.execution.repair_started', 's', 'c', ['a' => ['b' => 1]]);

        $this->assertEquals($original, MessageEnvelope::fromJson($original->toJson()));
    }

    public function test_from_json_rejects_non_object(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JSON object');

        MessageEnvelope::fromJson('"just a string"');
    }

    public function test_from_array_names_the_expected_keys_when_invalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('expected keys: message_id, type, saga_id');

        MessageEnvelope::fromArray(['type' => 'x']);
    }

    public function test_payload_value_returns_value_or_throws_with_context(): void
    {
        $envelope = MessageEnvelope::create('evt.billing.budget_created', 's', 'c', ['total' => 99]);

        $this->assertSame(99, $envelope->payloadValue('total'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("evt.billing.budget_created ({$envelope->messageId}) lacks payload key 'budget_id'");
        $envelope->payloadValue('budget_id');
    }
}
