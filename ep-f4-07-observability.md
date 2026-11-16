# Epic F4-07 — Observability for the distributed flows

Repos: all three services + `15SOAT-Fase1-kubernetes` (`observability/`).

## Done

- [x] dd-trace in every image (`DD_SERVICE` = `os-service` / `billing-service` / `execution-service`)
- [x] Workers trace CLI (`DD_TRACE_CLI_ENABLED=true` only on `*-worker`) so AMQP publish/consume spans carry the trace across services
- [x] JSON logs with `correlation_id` + `saga_id` (`InboxProcessor`, saga `Log::info` per step)
- [x] Monitors as code: `15SOAT-Fase1-kubernetes/observability/monitors.tf` (compensations, stuck steps, DLQ, API error rate) — `terraform validate` green

## Pending (needs the dev's Datadog account)

- [ ] `docker start dd-agent` on the host (Agent sends to Datadog SaaS — dev's call)
- [ ] Run `demo/e2e-saga.sh`, confirm one trace spans os-service → rabbitmq → billing/execution in APM
- [ ] `cd observability && DD_API_KEY=... DD_APP_KEY=... terraform apply`
- [ ] If AMQP spans do not link, set `DD_TRACE_PROPAGATION_STYLE=datadog,tracecontext` and re-check
