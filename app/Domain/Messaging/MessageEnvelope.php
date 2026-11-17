<?php

namespace App\Domain\Messaging;

use DateTimeImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Wire format shared by every service (see docs/asyncapi.yaml). The type is
 * also the RabbitMQ routing key, so `cmd.billing.create_budget` lands on the
 * billing queue and `evt.execution.*` on the OS queue.
 *
 * Example: MessageEnvelope::create('cmd.billing.create_budget', $sagaId, $correlationId, ['total' => 10.0]);
 */
final readonly class MessageEnvelope
{
    private const REQUIRED_KEYS = ['message_id', 'type', 'saga_id', 'correlation_id', 'occurred_at', 'payload'];

    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $messageId,
        public string $type,
        public string $sagaId,
        public string $correlationId,
        public string $occurredAt,
        public array $payload,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function create(string $type, string $sagaId, string $correlationId, array $payload): self
    {
        return new self(
            (string) Str::uuid(),
            $type,
            $sagaId,
            $correlationId,
            (new DateTimeImmutable)->format(DATE_ATOM),
            $payload,
        );
    }

    public static function fromJson(string $json): self
    {
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException("Message body must be a JSON object, got: {$json}");
        }

        return self::fromArray($decoded);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $missing = array_diff(self::REQUIRED_KEYS, array_keys($data));

        if ($missing !== [] || ! is_array($data['payload'])) {
            $expected = implode(', ', self::REQUIRED_KEYS);
            throw new InvalidArgumentException('Invalid envelope '.json_encode($data)."; expected keys: {$expected} (payload as object)");
        }

        return new self(
            (string) $data['message_id'],
            (string) $data['type'],
            (string) $data['saga_id'],
            (string) $data['correlation_id'],
            (string) $data['occurred_at'],
            $data['payload'],
        );
    }

    /** @return array{message_id: string, type: string, saga_id: string, correlation_id: string, occurred_at: string, payload: array<string, mixed>} */
    public function toArray(): array
    {
        return [
            'message_id' => $this->messageId,
            'type' => $this->type,
            'saga_id' => $this->sagaId,
            'correlation_id' => $this->correlationId,
            'occurred_at' => $this->occurredAt,
            'payload' => $this->payload,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    public function payloadValue(string $key): mixed
    {
        if (! array_key_exists($key, $this->payload)) {
            throw new InvalidArgumentException("Message {$this->type} ({$this->messageId}) lacks payload key '{$key}'");
        }

        return $this->payload[$key];
    }
}
