# Epic F4-07 — Observability for the distributed flows

Repos: all three services + `15SOAT-Fase1-kubernetes`. Decision: [ADR-016](docs/architecture/adrs/adr-016-local-apm-jaeger.md) (Datadog free plan has no APM → Jaeger).

## Done

- [x] dd-trace in every image (`DD_SERVICE` = `os-service` / `billing-service` / `execution-service`)
- [x] JSON logs with `correlation_id` + `saga_id`; `demo/trace-saga.sh <order_id>` merges them into one timeline
- [x] `trace-agent` (OTel Collector, datadog receiver, 128-bit ids) + Jaeger in `platform` (`k8s/platform/tracing.yaml`)
- [x] Traefik exports OTLP and injects `traceparent` into ForwardAuth and the upstream
- [x] Outbox carries trace context (`TraceContext` port, `outbox_messages.trace_context`, `outbox.relay` span); consumer handles inside `message.handle`
- [x] Billing/Execution API traces: `ServeCommandTracerEnv` passes `DD_*` to `php artisan serve` (synced lib)
- [x] Collector drops worker polling, kubelet `/up` probes and `laravel.event.handle` spans
- [x] Verified on kind with local images: one trace spans traefik → os → RabbitMQ → execution → os; Jaeger dependency graph matches the architecture
- [x] Datadog monitors (`observability/`) removed — never applied, need a paid plan

## Pending

- [ ] Merge the `f4-09-jaeger-tracing` PRs (kubernetes first, then OS, Billing, Execution); confirm the pipeline-built images produce the same traces
