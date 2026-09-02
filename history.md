# history.md

Summary of what already exists — past epics, technologies implemented, decisions made — for reference when scoping a new spec. Read only when explicitly asked to retrieve something from the past. Never load by default.

## Fase 1 — initial system

Laravel 13 REST API for a mechanic workshop: clients, vehicles, services, items (stock), service orders. Sanctum email+password auth for all 3 roles (`receptionist`, `mechanic`, `client`) via `AuthController`. PostgreSQL in prod, SQLite `:memory:` in tests. External integrations (payment, messaging) are stubs behind interfaces (`CLAUDE.md` rule 7), never real implementations.

## Fase 2 — hexagonal refactor + containerization + local K8s

Source: [`evolucao_fase2`](evolucao_fase2). Detailed spec/plan/tasks: [`docs/specs/fase2/`](docs/specs/fase2/) (nested convention, superseded by this flat one from Fase 3 onward — kept as-is, not migrated).

- Light hexagonal layering: `app/Domain/ServiceOrder`, `app/Application/{ServiceOrder,Ports}`, `app/Infrastructure/{Persistence/Eloquent,Messaging}`.
- New/changed OS APIs: open OS with services+items in one payload, quick status lookup, budget approval via webhook, priority-ordered listing, status update via e-mail (stub).
- Docker (multi-stage `Dockerfile`), `docker-compose.yml` for local dev.
- Kubernetes manifests in `/k8s` (Deployment, Service, ConfigMap, Secret, HPA, Postgres, migrate Job) — validated statically (`kubernetes_validate` against K8s 1.29 schema), run for real only against a local `kind` cluster.
- Terraform in `/infra`: `infra/cluster` provisions a local `kind` cluster (`tehcyx/kind` provider); `infra/database` applies the `/k8s` DB manifests into it via `kubernetes_manifest` (`hashicorp/kubernetes` provider) — `/k8s` stays the single source of truth for those resources, not duplicated in HCL.
- Single CI/CD workflow (`.github/workflows/ci-cd.yml`): test (PHPUnit, coverage gate `--min=80`) → docker build/push to GHCR → conditional deploy-k8s/deploy-db (skipped, not failed, when `KUBE_CONFIG` secret is absent — no real cloud cluster existed yet).
- Everything in Fase 2 ran locally (`kind`) or statically-validated only; never deployed to a real cloud.

## Fase 3 — corporate-grade cloud operation (in progress)

Source: [`evolucao_fase3`](evolucao_fase3). See `spec.md`'s epic queue and `backlog.md` for the epic breakdown. Architecture decisions land in `docs/architecture/{rfcs,adrs}/` as each epic makes them, not batched at the end.

Key decisions already made (see `docs/architecture/adrs/adr-001-dokploy-as-cloud.md` and `rfc-001-cloud-strategy.md` for full rationale):
- Dokploy (self-hosted PaaS) is the primary cloud, standing in for the "Kubernetes cluster with scalability" requirement via its own orchestrator (Swarm/Compose) — no real cloud K8s cluster provisioned. `/k8s` and `/infra` from Fase 2 are kept as the literal local K8s reference implementation, not removed.
- The CPF-auth Function Serverless is real AWS Lambda (free tier) — the one piece on a second cloud.
- Project splits into 4 repos: this one (`15SOAT-Fase1`, unchanged) plus `workshop-os-lambda-auth`, `workshop-os-infra-kubernetes`, `workshop-os-infra-database`.
