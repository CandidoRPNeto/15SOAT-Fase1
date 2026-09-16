# Epic 6 — Observabilidade (Datadog)

**Depende de**: Epic 4, Epic 5.
**Repos de código**: `15SOAT-Fase1`, `15SOAT-Fase1-kubernetes`.
**Objetivo**: Datadog Agent coletando métricas de container, monitors pros itens de `evolucao_fase3` que dava pra construir e validar sem uma conta Datadog conectada nesta sessão.

**Nota de sequenciamento**: branch empilhada
(`feature/2026/09/epic6-observability` a partir de
`feature/2026/09/epic5-cpf-auth`), mesma lógica dos epics anteriores.

## Decisão arquitetural

- [x] `docs/architecture/adrs/adr-009-datadog-agent-placement.md` — Agent via `dokploy_compose`, escopo real implementado vs. lacunas conscientes (synthetic uptime check, latência como métrica Datadog nativa)

## App principal (`15SOAT-Fase1`)

- [x] `App\Http\Middleware\LogRequestLatency` — `duration_ms` no contexto de log, header `X-Response-Time-Ms` + teste de feature
- [x] Registrado em `bootstrap/app.php` (mais externo que `AssignCorrelationId`, mede a requisição inteira)
- [x] Suíte completa: 150 passou (era 149 depois do Epic 5)

## Infra (`15SOAT-Fase1-kubernetes`)

- [x] `dokploy_compose.datadog_agent` — Agent oficial, `docker.sock` montado, coleta de logs habilitada
- [x] `datadog_monitor` × 3 — CPU, memória (ambos com `notify_no_data` como sinal de uptime), falhas de OS (log alert)
- [x] `terraform validate` passou contra os providers reais (`vanillauys/dokploy` + `DataDog/datadog` 4.20.0)
- [x] `deploy.yml` atualizado com os secrets `DD_API_KEY`/`DD_APP_KEY`
- [x] README atualizado (diagrama incluindo Datadog)

## Lacunas documentadas (ver ADR-009, não escondidas)

- [ ] Synthetic HTTP check externo pro uptime real (`datadog_synthetics_test`) — schema grande demais pra verificar com segurança nesta sessão
- [ ] `datadog_logs_metric` transformando `duration_ms` numa métrica Datadog nativa (painel/monitor de latência) — mesmo motivo

## Fechamento do epic

- [x] Push das branches (sem PR ainda) — `15SOAT-Fase1`, `15SOAT-Fase1-kubernetes`
- [ ] Apagar este arquivo e a linha em `spec.md` só depois dos PRs mergeados
