# ADR-009: Datadog Agent como serviço Dokploy + escopo real do Epic 6

**Status**: Aceita
**Data**: 2026-09-02

## Contexto

`evolucao_fase3` pede Datadog monitorando: latência das APIs, consumo de
recursos do "Kubernetes" (aqui, containers Dokploy — ver
[ADR-001](adr-001-dokploy-as-cloud.md)), healthchecks/uptime, alertas de
falha no processamento de OS, e logs estruturados correlacionados (já
resolvido no Epic 4 — `AssignCorrelationId`, canal `json`). Falta decidir
onde o Agent roda e o que efetivamente dá pra implementar sem uma conta
Datadog real conectada nesta sessão de desenvolvimento (usuário confirmou
ter conta/trial, mas as credenciais não foram compartilhadas — correto, não
deveriam ser coladas numa conversa).

## Decisão

**Agent como `dokploy_compose`** (provider `vanillauys/dokploy`, mesmo do
banco/app — [ADR-005](adr-005-dokploy-terraform-provider.md)), imagem
oficial `datadog/agent`, no mesmo projeto/environment do banco e da app
([RFC-002](../rfcs/rfc-002-managed-database-strategy.md)). Monta
`/var/run/docker.sock` (read-only) — dá acesso a métricas de CPU/memória de
**todos** os containers do host automaticamente, sem instrumentar cada
serviço individualmente.

**Escopo real implementado nesta sessão** (o que pôde ser verificado de
alguma forma, ainda que sem credenciais reais):

| Requisito | Implementação | Verificação possível |
|---|---|---|
| Logs estruturados + correlação | `AssignCorrelationId` (Epic 4) | Testes reais, já mergeados |
| Latência das APIs | `LogRequestLatency` — `duration_ms` no log JSON | Teste real (`RequestLatencyTest`) |
| CPU/memória de containers | `datadog_monitor` (`docker.cpu.usage`, `docker.mem.rss`) — Agent coleta via docker.sock, zero instrumentação extra | `terraform validate` contra o schema real do provider Datadog |
| Falhas no processamento de OS | `datadog_monitor` tipo `log alert` sobre `service:workshop-os-app status:error` | `terraform validate` |
| Healthchecks/uptime | `notify_no_data=true` nos monitors de container — ausência de dados = container fora do ar | `terraform validate` — **interpretação pragmática, não um synthetic HTTP check** |

## Consequências

- Positivas: cobre 4 dos 5 itens do requisito com Terraform validado de
  verdade contra os providers reais (Dokploy + Datadog), sem inventar
  schema; o Agent via `docker.sock` dá visibilidade de recursos de
  qualquer container novo automaticamente, sem precisar tocar Terraform a
  cada novo serviço.
- Negativas / trade-offs conscientemente aceitos, não escondidos:
  - **Uptime real (synthetic HTTP check externo)** não foi implementado —
    `datadog_synthetics_test` tem um schema grande o bastante (~85KB de
    documentação) que não foi possível verificar com segurança nesta
    sessão; a interpretação pragmática adotada (`notify_no_data`) cobre
    "o serviço parou de responder", não "o serviço responde mas com
    erro/lentidão vista de fora" — diferença real, registrada aqui em vez
    de maquiada.
  - **Latência como métrica Datadog nativa** (p50/p95 num painel/monitor)
    não foi implementada — exigiria um `datadog_logs_metric` extraindo
    `duration_ms` dos logs, mesmo motivo (schema não verificado). O que
    existe é o dado no log (`duration_ms`), pronto pra virar métrica
    quando alguém configurar isso — não é um requisito não atendido por
    preguiça, é uma fronteira clara entre "o que dá pra construir e testar
    sem a API real" e "o que precisa de uma conta conectada de verdade".
  - `terraform apply` real não foi rodado — precisa de `DD_API_KEY`/
    `DD_APP_KEY` do usuário, não fornecidas nesta sessão (corretamente).
