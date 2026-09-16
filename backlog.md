# backlog.md

Future epic ideas, not yet started. Read only when scoping the next epic.

Epic 1 is already in flight — see `spec.md`'s epic queue and `ep-multirepo-foundation.md`, not duplicated here. Source requirement for all epics below: [`evolucao_fase3`](evolucao_fase3).

Order reflects dependencies, not the literal order of `evolucao_fase3`'s bullet list — see rationale under each entry. RFCs/ADRs are written in the epic that makes the decision, not deferred to a final docs epic.

## Epic 2 — DB infra IaC

- **Goal**: managed Postgres via Terraform against the Dokploy provider.
- **Repo**: `workshop-os-infra-database`.
- **Depends on**: Epic 1 (repo + branch protection + CI skeleton must exist).
- **Produces**: `rfc-002-managed-database-strategy.md`.
- **Why before Epic 3**: nothing downstream is testable end-to-end without a real Postgres instance. Also the lower-risk Terraform work — it mirrors what `infra/database` already did in Fase 2 (apply manifests via a provider), good for proving the Dokploy Terraform provider works before building more on top of it.

## Epic 3 — App/K8s infra IaC

- **Goal**: Dokploy app-hosting resource, domain/Traefik routing, deploy webhook — the Terraform-provisioned "platform" the main app deploys onto.
- **Repo**: `workshop-os-infra-kubernetes`.
- **Depends on**: Epic 1, Epic 2 (DB must exist for the app resource to point at).
- **Produces**: `adr-005-dokploy-terraform-provider.md` (community-maintained, not HashiCorp-official — version-pin, document a manual-UI fallback), `adr-006-scaling-approach.md` (Swarm replica count vs. HPA — ties back to `adr-001`'s caveat).

## Epic 4 — App cloud adaptation

- **Goal**: get the *existing* app (no new features yet) actually running on the new cloud infra. Structured JSON logs with a correlation/request ID, trusted-proxy config (for Dokploy's Traefik), CI/CD deploy stage targeting Dokploy on `main`/`homolog` (alongside, not replacing, the existing `KUBE_CONFIG`-gated kind path).
- **Repo**: `15SOAT-Fase1` (this repo).
- **Depends on**: Epic 1, Epic 2, Epic 3.
- **Produces**: no new RFC/ADR — implementation epic.
- **Why before Epic 5**: gives a real, reachable HTTPS endpoint before building the Lambda/JWT flow against it. Building auth against infra that was never actually deployed risks discovering Dokploy/Traefik surprises (proxy headers, TLS, env wiring) at the same time as debugging new auth — worse than sequencing them.

## Epic 5 — Auth Lambda + API Gateway + JWT validation

- **Goal**: AWS Lambda validating CPF, checking client existence/status, issuing a JWT; AWS API Gateway fronting it; Laravel-side guard validating that JWT for protected routes. One epic because Lambda's JWT claims/alg/TTL and Laravel's validation guard have to agree — splitting risks a false "done" checkpoint. Split into `5a-lambda-auth`/`5b-jwt-gateway` if stricter granularity is wanted later.
- **Repos**: `workshop-os-lambda-auth` + `15SOAT-Fase1`.
- **Depends on**: Epic 1, Epic 4 (needs a real deployed app to protect).
- **Produces**: `rfc-003-cpf-auth-strategy.md` (must settle: DB access pattern — internal Laravel endpoint vs. direct read-only Postgres connection from Lambda; JWT alg — HS256 shared-secret vs. RS256 public-key, RS256 leans favored to avoid AWS/Dokploy secret-sync; whether this is additive to existing Sanctum email/password login or a replacement for the `client` role — additive is the default unless the dev says otherwise; new `status` column needed on `users`, none exists today), `adr-007-aws-api-gateway.md`, `adr-008-jwt-validation-layer.md`, sequence diagrams (auth flow, OS-opening-through-gateway flow) in `docs/architecture/diagrams/`.

## Epic 6 — Observability (Datadog)

- **Goal**: Datadog Agent (Dokploy VPS side) + Lambda extension/layer (AWS side) — APM latency, container CPU/mem as the K8s-resource-consumption equivalent, healthchecks/uptime, alerts for service-order-processing failures, structured-log correlation (ties into Epic 4's correlation ID).
- **Repos**: `15SOAT-Fase1` + `workshop-os-infra-kubernetes` + `workshop-os-infra-database`.
- **Depends on**: Epic 4, Epic 5 (covers the whole system, including the new auth flow's latency/errors, in one pass instead of instrumenting twice).
- **Produces**: `adr-009-datadog-agent-placement.md`.
- **Account**: user already has a Datadog account/trial — no setup step needed.

## Epic 7 — Final architecture documentation

- **Goal**: cloud-wide component diagram (accurate only once everything above exists), README consolidation across all 4 repos (purpose, stack, run/deploy steps, per-repo diagram, Swagger/Postman link — placeholders from Epic 1 get filled in here).
- **Repos**: all 4 (docs only).
- **Depends on**: Epics 1–6.
- **Produces**: nothing new in `rfcs`/`adrs` — consolidation only, per explicit instruction to not batch decision records at the end.
