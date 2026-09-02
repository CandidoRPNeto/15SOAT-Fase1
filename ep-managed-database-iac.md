# Epic 2 — DB infra IaC

**Depende de**: Epic 1 (`ep-multirepo-foundation.md`, ainda com merge do PR #1 pendente — ver nota abaixo).
**Repo de código**: [`workshop-os-infra-database`](https://github.com/CandidoRPNeto/workshop-os-infra-database).
**Objetivo**: Postgres gerenciado via Terraform (`vanillauys/dokploy` provider) — projeto Dokploy compartilhado com o Epic 3, banco `workshop-os-postgres`.

**Nota de sequenciamento**: por pedido explícito do usuário, este epic foi
iniciado antes do merge do PR #1 (epic 1) — branches empilhadas
(`feature/2026/09/epic2-managed-database-rfc` a partir de
`feature/2026/09/specdriven-fase3-foundation`), PRs a abrir e mergear em
ordem depois. Ver `spec.md` para a fila completa.

## Decisão arquitetural

- [x] `docs/architecture/rfcs/rfc-002-managed-database-strategy.md` —
      provider `vanillauys/dokploy` v0.10.2, ownership do `dokploy_project`
      neste repo, lacuna de state não compartilhado documentada

## Terraform (`workshop-os-infra-database`)

- [ ] `versions.tf` — `required_providers { dokploy = { source =
      "vanillauys/dokploy", version = "0.10.2" } }`
- [ ] `main.tf` — `dokploy_project.workshop_os` + `dokploy_postgres.workshop_os`
      (`postgres:16-alpine`, database/user `workshop_os`)
- [ ] `variables.tf` — `db_password` (sensível, sem default), `dokploy_endpoint`/`dokploy_api_key` opcionais (default: variáveis de ambiente)
- [ ] `outputs.tf` — `project_id`, `environment_id`, `postgres_id`
- [ ] README do repo preenchido (propósito/tecnologias/execução — placeholders do epic 1)
- [ ] CI: `terraform validate` continua passando com os recursos reais (sem `apply` — sem credenciais Dokploy disponíveis nesta sessão)

## Fechamento do epic

- [ ] Push da branch (sem PR ainda, conforme pedido do usuário)
- [ ] Apagar este arquivo e a linha correspondente em `spec.md` só depois do PR mergeado
