<?php

namespace Tests\Unit;

use App\Application\Messaging\InboxProcessor;
use App\Application\Ports\MessageRouter;
use App\Domain\Messaging\MessageEnvelope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class InboxProcessorTest extends TestCase
{
    use RefreshDatabase;

    private function router(bool $fail = false): MessageRouter
    {
        return new class($fail) implements MessageRouter
        {
            /** @var list<string> */
            public array $routed = [];

            public function __construct(private readonly bool $fail) {}

            public function bindings(): array
            {
                return ['evt.#'];
            }

            public function route(MessageEnvelope $envelope): void
            {
                if ($this->fail) {
                    throw new RuntimeException('handler failed');
                }
                $this->routed[] = $envelope->messageId;
            }
        };
    }

    public function test_routes_message_once_and_skips_redelivery(): void
    {
        $router = $this->router();
        $processor = new InboxProcessor($router);
        $envelope = MessageEnvelope::create('evt.x', 's', 'c', []);

        $this->assertTrue($processor->process($envelope));
        $this->assertFalse($processor->process($envelope));
        $this->assertSame([$envelope->messageId], $router->routed);
        $this->assertDatabaseHas('processed_messages', ['message_id' => $envelope->messageId]);
    }

    public function test_failed_handler_does_not_mark_message_processed(): void
    {
        $processor = new InboxProcessor($this->router(fail: true));
        $envelope = MessageEnvelope::create('evt.x', 's', 'c', []);

        try {
            $processor->process($envelope);
            $this->fail('Expected handler exception');
        } catch (RuntimeException) {
            $this->assertDatabaseMissing('processed_messages', ['message_id' => $envelope->messageId]);
        }
    }
}
