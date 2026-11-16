# spec.md

Entry point for any spec-driven action: starting or closing an epic, adding a rule, or resolving a gap `CLAUDE.md` doesn't cover. Read this file first. Descend into a linked file only when the specific need arises — don't read speculatively.

## Source requirement

- [`evolucao_fase4`](evolucao_fase4) — Fase 4 requirements (microservices, Saga, per-service CI/CD). Every epic below traces back to it.

## Files

- `rules.md` — decision log. A rule is added only when the dev corrects a proposed approach and explicitly asks to save it. Consult only when a question isn't answered by this file, the active `ep-*.md`, or `CLAUDE.md`. If `rules.md` doesn't answer it either, ask the dev — never guess.
- `history.md` — summary of past phases. Read only when explicitly asked to retrieve something from the past.
- `ep-<name>.md` — an active epic's task list + references to code and other `.md` files. One file per epic in flight.
- `docs/architecture/{rfcs,adrs,diagrams}/` — decisions and diagrams. Fase 4 decisions: RFC-004, ADR-010..015.

## Fase 4 target (summary — details live in the ADRs)

| Service | Repo | DB | Owns |
|---|---|---|---|
| OS Service | `15SOAT-Fase1` | PostgreSQL | auth, clients, vehicles, catalog/stock, service orders, status history, saga orchestrator |
| Billing Service | `15SOAT-Fase1-billing` | PostgreSQL | budgets, approval, payments (Mercado Pago test credentials) |
| Execution Service | `15SOAT-Fase1-execution` | MongoDB | diagnosis/repair queue, execution timeline |
| Platform | `15SOAT-Fase1-kubernetes` | — | RabbitMQ, Traefik (ForwardAuth), self-hosted runner setup, demo scripts |

- Saga: orchestrated, in OS Service — [ADR-010](docs/architecture/adrs/adr-010-saga-orchestration.md).
- Messaging: RabbitMQ + outbox/inbox — [ADR-011](docs/architecture/adrs/adr-011-rabbitmq-async-messaging.md).
- Deploy: GitHub Actions self-hosted runner → local kind cluster `workshop-os` — [ADR-014](docs/architecture/adrs/adr-014-self-hosted-runner-kind.md).

## Epic queue (Fase 4)

Order reflects dependencies. Branch per epic per repo: `feature/2026/11/<epic>`. Done: F4-01 (spec reset, RFC-004, ADR-010..015), F4-03 (OS messaging + saga; BDD runs in PHPUnit via `behat/gherkin` because Behat 3 does not install on Symfony 8).

2. `ep-f4-02-platform.md` — RabbitMQ, Traefik + ForwardAuth, namespaces, runner setup script, demo scripts (`15SOAT-Fase1-kubernetes`).
4. `ep-f4-04-billing-service.md` — new repo; budgets, approval, Mercado Pago gateway, payment polling.
5. `ep-f4-05-execution-service.md` — new repo; MongoDB queue, diagnosis/repair endpoints, timeline.
6. `ep-f4-06-k8s-cicd.md` — per-service `k8s/`, CI/CD workflows, SonarCloud, branch protection, first runner deploy.
7. `ep-f4-07-observability.md` — dd-trace per service, saga logs, Datadog monitors, distributed trace check.
8. `ep-f4-08-docs-delivery.md` — diagrams + render script, `docs/fase4/entrega.md`, READMEs, Postman, coverage evidence, video `roteiro.md`.

## Epic lifecycle

1. Starting an epic: create `ep-<name>.md` — tasks plus references to code/other files only, no duplicated business-rule prose.
2. Working an epic: update task statuses as you go.
3. Finishing an epic: once every task is done and verified, delete `ep-<name>.md` and remove its line from the queue above. `git log` is the record. Only add to `rules.md` if the dev explicitly asks.
4. Closing Fase 4: summarize it in `history.md` (done in `ep-f4-08`).

## File-size rule

Any file here except `ep-*.md` that exceeds ~300 lines gets split: move the overflow into `<file>-<topic>.md`, leaving one line in the main file — a link plus a one-sentence summary.

## Language

- Use US English.
- Write in a concise LLM-friendly style.
- Use short imperative sentences.
- One rule per bullet.
- Prefer consistent terminology over synonyms.
- Avoid filler, repetition and unnecessary adjectives.
- Use technical terms and structured lists.
- Keep instructions deterministic and unambiguous.
