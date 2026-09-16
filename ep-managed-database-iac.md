# Epic 2 — DB infra IaC

**Depende de**: Epic 1 (`ep-multirepo-foundation.md`, ainda com merge do PR #1 pendente — ver nota abaixo).
**Repo de código**: [`15SOAT-Fase1-database`](https://github.com/CandidoRPNeto/15SOAT-Fase1-database).
**Objetivo**: Postgres gerenciado via Terraform (`vanillauys/dokploy` provider) — projeto Dokploy compartilhado com o Epic 3, banco `15SOAT-Fase1-postgres`.

**Nota de sequenciamento**: por pedido explícito do usuário, este epic foi
iniciado antes do merge do PR #1 (epic 1) — branches empilhadas
(`feature/2026/09/epic2-managed-database-rfc` a partir de
`feature/2026/09/specdriven-fase3-foundation`), PRs a abrir e mergear em
ordem depois. Ver `spec.md` para a fila completa.

## Decisão arquitetural

- [x] `docs/architecture/rfcs/rfc-002-managed-database-strategy.md` —
      provider `vanillauys/dokploy` v0.10.2, ownership do `dokploy_project`
      neste repo, lacuna de state não compartilhado documentada

## Terraform (`15SOAT-Fase1-database`)

- [x] `versions.tf` — `required_providers { dokploy = { source =
      "vanillauys/dokploy", version = "0.10.2" } }`
- [x] `main.tf` — `dokploy_project.fase1` + `dokploy_postgres.fase1`
      (`postgres:16-alpine`, database/user `workshop_os`)
- [x] `variables.tf` — `db_password` (sensível, sem default); endpoint/api_key vêm de `DOKPLOY_ENDPOINT`/`DOKPLOY_API_KEY` (env, sem variável Terraform própria)
- [x] `outputs.tf` — `project_id`, `environment_id`, `postgres_id`, `postgres_status`
- [x] README do repo preenchido (propósito/tecnologias/execução/diagrama)
- [x] `.tool-versions` (terraform 1.15.8, alinhado com o repo principal) + CI atualizado para a mesma versão
- [x] `terraform init`/`validate` rodados de verdade contra o provider real (0.10.2) — passou. `apply` não rodado (sem servidor Dokploy/API key disponíveis nesta sessão)

## Fechamento do epic

- [x] Push das duas branches (`15SOAT-Fase1` e `15SOAT-Fase1-database`), sem PR ainda, conforme pedido do usuário
- [ ] Apagar este arquivo e a linha correspondente em `spec.md` só depois dos PRs mergeados
