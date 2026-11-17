# history.md

Summary of what already exists — past epics, technologies implemented, decisions made — for reference when scoping a new spec. Read only when explicitly asked to retrieve something from the past. Never load by default.

## Fase 1 — initial system

Laravel 13 REST API for a mechanic workshop: clients, vehicles, services, items (stock), service orders. Sanctum email+password auth for all 3 roles (`receptionist`, `mechanic`, `client`) via `AuthController`. PostgreSQL in prod, SQLite `:memory:` in tests. External integrations (payment, messaging) are stubs behind interfaces (`CLAUDE.md` rule 7), never real implementations.

## Fase 2 — hexagonal refactor + containerization + local K8s

Source requirement and the nested spec/plan/tasks docs were local-only (git-ignored) and removed at the Fase 4 spec reset.

- Light hexagonal layering: `app/Domain/ServiceOrder`, `app/Application/{ServiceOrder,Ports}`, `app/Infrastructure/{Persistence/Eloquent,Messaging}`.
- New/changed OS APIs: open OS with services+items in one payload, quick status lookup, budget approval via webhook, priority-ordered listing, status update via e-mail (stub).
- Docker (multi-stage `Dockerfile`), `docker-compose.yml` for local dev.
- Kubernetes manifests in `/k8s` (Deployment, Service, ConfigMap, Secret, HPA, Postgres, migrate Job) — validated statically (`kubernetes_validate` against K8s 1.29 schema), run for real only against a local `kind` cluster.
- Terraform in `/infra`: `infra/cluster` provisions a local `kind` cluster (`tehcyx/kind` provider); `infra/database` applies the `/k8s` DB manifests into it via `kubernetes_manifest` (`hashicorp/kubernetes` provider) — `/k8s` stays the single source of truth for those resources, not duplicated in HCL.
- Single CI/CD workflow (`.github/workflows/ci-cd.yml`): test (PHPUnit, coverage gate `--min=80`) → docker build/push to GHCR → conditional deploy-k8s/deploy-db (skipped, not failed, when `KUBE_CONFIG` secret is absent — no real cloud cluster existed yet).
- Everything in Fase 2 ran locally (`kind`) or statically-validated only; never deployed to a real cloud.

## Fase 3 — corporate-grade cloud operation (closed)

Source: `evolucao_fase3`, removed at the Fase 4 spec reset — read it with `git show 91319ed:evolucao_fase3`. Epic files (`ep-*.md`) and `backlog.md` were removed at the same point; `git show 91319ed:<file>` recovers any of them.

- Dokploy (self-hosted PaaS) chosen as primary cloud ([ADR-001](docs/architecture/adrs/adr-001-dokploy-as-cloud.md)); `/k8s` + `/infra` kept as the local kind reference ([ADR-003](docs/architecture/adrs/adr-003-keep-local-k8s-reference.md)).
- 4 repos ([ADR-002](docs/architecture/adrs/adr-002-four-repo-split.md)): `15SOAT-Fase1` (app), `15SOAT-Fase1-lambda` (CPF auth → RS256 JWT), `15SOAT-Fase1-kubernetes` (Terraform Dokploy app/domain/Datadog), `15SOAT-Fase1-database` (Terraform Dokploy Postgres). Branch protection + CI on all.
- App: `client_jwt` guard additive to Sanctum ([ADR-008](docs/architecture/adrs/adr-008-jwt-validation-layer.md)), `/internal/clients/cpf-lookup` for the Lambda, correlation ID + latency headers, JSON logs.
- Observability: Datadog Agent + monitors ([ADR-009](docs/architecture/adrs/adr-009-datadog-agent-placement.md)); dd-trace-php inside the image.
- Video ran fully local: kind cluster `workshop-os`, Traefik as in-cluster gateway, simulated Lambda/API Gateway. No real `terraform apply` against Dokploy/AWS.
- PRs were left stacked (one per epic) for the dev to merge in order.

## Fase 4 — microservices + Saga (in progress)

See `spec.md`.
