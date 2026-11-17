<?php

namespace App\Application\Ports;

/**
 * Carries the active trace across the transactional outbox (ADR-016): the
 * recorder captures it with the message, the relay publishes inside a span
 * that continues it, so one trace spans HTTP → outbox → RabbitMQ → consumer.
 *
 * Example: $relay->publish() inside $trace->continueTrace($row->trace_context, 'outbox.relay', $row->type, fn () => ...);
 */
interface TraceContext
{
    /** @return array<string, string> propagation headers of the active span; [] when nothing is traced */
    public function capture(): array;

    /**
     * Runs $work inside a new span named $operation whose parent is the span
     * $headers came from. Without headers it just runs $work.
     *
     * @template T
     *
     * @param  array<string, string>  $headers
     * @param  callable(): T  $work
     * @return T
     */
    public function continueTrace(array $headers, string $operation, string $resource, callable $work): mixed;

    /**
     * Runs $work inside a child span of the active one, filed under this
     * service. The tracer files the AMQP deliver span (and everything that
     * inherits from it) under the broker, so the consumer wraps its handler.
     *
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    public function inServiceSpan(string $operation, string $resource, callable $work): mixed;
}
