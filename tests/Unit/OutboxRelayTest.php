<?php

namespace Tests\Unit;

use App\Application\Messaging\OutboxRecorder;
use App\Application\Messaging\OutboxRelay;
use App\Domain\Messaging\MessageEnvelope;
use App\Models\OutboxMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\InMemoryMessagePublisher;
use Tests\TestCase;

class OutboxRelayTest extends TestCase
{
    use RefreshDatabase;

    private InMemoryMessagePublisher $publisher;

    private OutboxRelay $relay;

    protected function setUp(): void
    {
        parent::setUp();
        $this->publisher = new InMemoryMessagePublisher;
        $this->relay = new OutboxRelay($this->publisher);
    }

    private function record(string $type): void
    {
        (new OutboxRecorder)->record(MessageEnvelope::create($type, 'saga', 'corr', []));
    }

    public function test_publishes_pending_messages_in_insertion_order_and_marks_them(): void
    {
        $this->record('cmd.a');
        $this->record('cmd.b');

        $this->assertSame(2, $this->relay->relayPending(10));
        $this->assertSame(['cmd.a', 'cmd.b'], $this->publisher->publishedTypes());
        $this->assertSame(0, OutboxMessage::whereNull('published_at')->count());
    }

    public function test_already_published_rows_are_not_sent_again(): void
    {
        $this->record('cmd.a');
        $this->relay->relayPending(10);

        $this->assertSame(0, $this->relay->relayPending(10));
        $this->assertCount(1, $this->publisher->published);
    }

    public function test_broker_failure_keeps_row_pending_and_counts_attempt(): void
    {
        $this->record('cmd.a');
        $this->publisher->brokerDown = true;

        $this->assertSame(0, $this->relay->relayPending(10));
        $row = OutboxMessage::firstOrFail();
        $this->assertNull($row->published_at);
        $this->assertSame(1, $row->attempts);

        $this->publisher->brokerDown = false;
        $this->assertSame(1, $this->relay->relayPending(10));
    }

    public function test_respects_batch_limit(): void
    {
        $this->record('cmd.a');
        $this->record('cmd.b');

        $this->assertSame(1, $this->relay->relayPending(1));
    }
}
