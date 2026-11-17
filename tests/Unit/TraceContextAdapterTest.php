<?php

namespace Tests\Unit;

use App\Application\Ports\TraceContext;
use App\Infrastructure\Tracing\DatadogTraceContext;
use App\Infrastructure\Tracing\NullTraceContext;
use Tests\TestCase;

class TraceContextAdapterTest extends TestCase
{
    public function test_null_context_captures_nothing_and_runs_the_work(): void
    {
        $trace = new NullTraceContext;

        $this->assertSame([], $trace->capture());
        $this->assertSame(42, $trace->continueTrace(['traceparent' => 'x'], 'outbox.relay', 'cmd.a', fn () => 42));
    }

    public function test_null_context_service_span_runs_the_work(): void
    {
        $this->assertSame('ok', (new NullTraceContext)->inServiceSpan('message.handle', 'evt.x', fn () => 'ok'));
    }

    public function test_container_binds_datadog_only_when_the_extension_is_loaded(): void
    {
        $expected = DatadogTraceContext::available() ? DatadogTraceContext::class : NullTraceContext::class;
        $this->assertInstanceOf($expected, $this->app->make(TraceContext::class));
    }

    public function test_datadog_context_without_headers_runs_the_work_untraced(): void
    {
        $this->assertSame('done', (new DatadogTraceContext)->continueTrace([], 'outbox.relay', 'cmd.a', fn () => 'done'));
    }
}
