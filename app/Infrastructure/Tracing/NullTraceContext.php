<?php

namespace App\Infrastructure\Tracing;

use App\Application\Ports\TraceContext;

/**
 * Used where the ddtrace extension is not loaded (tests, host PHP): no
 * context to capture, work runs untraced.
 *
 * Example: (new NullTraceContext)->continueTrace([], 'outbox.relay', 'cmd.x', fn () => 1); // 1
 */
final class NullTraceContext implements TraceContext
{
    public function capture(): array
    {
        return [];
    }

    public function continueTrace(array $headers, string $operation, string $resource, callable $work): mixed
    {
        return $work();
    }

    public function inServiceSpan(string $operation, string $resource, callable $work): mixed
    {
        return $work();
    }
}
