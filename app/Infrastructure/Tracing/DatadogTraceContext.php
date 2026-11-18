<?php

namespace App\Infrastructure\Tracing;

use App\Application\Ports\TraceContext;

/**
 * dd-trace-php adapter. The headers carry both Datadog and W3C `traceparent`
 * styles, so any OTLP backend (Jaeger, via the collector's datadog receiver)
 * links the spans too. Bound only when the extension is loaded.
 *
 * Example: $headers = (new DatadogTraceContext)->capture(); // ['x-datadog-trace-id' => '…', 'traceparent' => '…']
 */
final class DatadogTraceContext implements TraceContext
{
    public static function available(): bool
    {
        return extension_loaded('ddtrace');
    }

    public function capture(): array
    {
        return \DDTrace\generate_distributed_tracing_headers();
    }

    public function continueTrace(array $headers, string $operation, string $resource, callable $work): mixed
    {
        if ($headers === []) {
            return $work();
        }

        // A fresh trace whose root adopts the stored parent: the relay loop
        // itself is not traced, so without this the publish span is orphaned.
        $span = \DDTrace\start_trace_span();
        \DDTrace\consume_distributed_tracing_headers($headers);
        $span->name = $operation;
        $span->resource = $resource;

        try {
            return $work();
        } finally {
            \DDTrace\close_span();
        }
    }

    public function inServiceSpan(string $operation, string $resource, callable $work): mixed
    {
        $span = \DDTrace\start_span();
        $span->name = $operation;
        $span->resource = $resource;
        // Explicit: dd-trace's AMQP posthook rewrites the deliver span (the
        // consumer's root) to "amqp" after the handler ran (dd-trace-php 1.27).
        $service = getenv('DD_SERVICE');
        if ($service !== false && $service !== '') {
            $span->service = $service;
        }

        try {
            return $work();
        } finally {
            \DDTrace\close_span();
        }
    }
}
