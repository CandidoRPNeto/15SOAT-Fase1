<?php

namespace Tests\Unit;

use App\Application\Messaging\OutboxRecorder;
use App\Application\Messaging\OutboxRelay;
use App\Domain\Messaging\MessageEnvelope;
use App\Models\OutboxMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\InMemoryMessagePublisher;
use Tests\Fakes\RecordingTraceContext;
use Tests\TestCase;

/** ADR-016: the trace survives the outbox hop. */
class OutboxTraceContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_recorder_stores_the_active_trace_with_the_message(): void
    {
        $trace = new RecordingTraceContext(['traceparent' => '00-abc-def-01']);

        (new OutboxRecorder($trace))->record(MessageEnvelope::create('cmd.a', 'saga', 'corr', []));

        $this->assertSame(['traceparent' => '00-abc-def-01'], OutboxMessage::firstOrFail()->trace_context);
    }

    public function test_relay_publishes_inside_a_span_continuing_the_stored_trace(): void
    {
        $trace = new RecordingTraceContext(['traceparent' => '00-abc-def-01']);
        (new OutboxRecorder($trace))->record(MessageEnvelope::create('cmd.a', 'saga', 'corr', []));
        $publisher = new InMemoryMessagePublisher;

        (new OutboxRelay($publisher, $trace))->relayPending(10);

        $this->assertSame([['headers' => ['traceparent' => '00-abc-def-01'], 'operation' => 'outbox.relay', 'resource' => 'cmd.a']], $trace->continued);
        $this->assertSame(['cmd.a'], $publisher->publishedTypes());
    }

    public function test_rows_recorded_without_tracing_relay_with_empty_context(): void
    {
        (new OutboxRecorder)->record(MessageEnvelope::create('cmd.a', 'saga', 'corr', []));
        $trace = new RecordingTraceContext;

        (new OutboxRelay(new InMemoryMessagePublisher, $trace))->relayPending(10);

        $this->assertSame([], $trace->continued[0]['headers']);
    }
}
