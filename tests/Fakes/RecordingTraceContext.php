<?php

namespace Tests\Fakes;

use App\Application\Ports\TraceContext;

/** Returns fixed headers on capture() and records every continueTrace() call. */
final class RecordingTraceContext implements TraceContext
{
    /** @var list<array{headers: array<string, string>, operation: string, resource: string}> */
    public array $continued = [];

    /** @var list<string> operations of inServiceSpan() calls */
    public array $serviceSpans = [];

    /** @param array<string, string> $headers */
    public function __construct(private readonly array $headers = ['traceparent' => '00-0af7651916cd43dd8448eb211c80319c-b7ad6b7169203331-01']) {}

    public function capture(): array
    {
        return $this->headers;
    }

    public function continueTrace(array $headers, string $operation, string $resource, callable $work): mixed
    {
        $this->continued[] = compact('headers', 'operation', 'resource');

        return $work();
    }

    public function inServiceSpan(string $operation, string $resource, callable $work): mixed
    {
        $this->serviceSpans[] = "{$operation} {$resource}";

        return $work();
    }
}
