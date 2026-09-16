# Epic 3 — App/K8s infra IaC

**Depende de**: Epic 1, Epic 2 (`environment_id`/`project_id` exportados por `workshop-os-infra-database`).
**Repo de código**: [`workshop-os-infra-kubernetes`](https://github.com/CandidoRPNeto/workshop-os-infra-kubernetes).
**Objetivo**: recurso de app do Dokploy (`dokploy_application`) + domínio/roteamento (`dokploy_domain`), mesmo projeto/environment do banco.

**Nota de sequenciamento**: mesma lógica do Epic 2 — branch empilhada
(`feature/2026/09/epic3-app-infra-adrs` a partir de
`feature/2026/09/epic2-managed-database-rfc`), PR a abrir/mergear em ordem
depois do Epic 1 e do Epic 2.

## Decisões arquiteturais

- [x] `docs/architecture/adrs/adr-005-dokploy-terraform-provider.md` — mesmo provider do Epic 2, versão pinada
- [x] `docs/architecture/adrs/adr-006-scaling-approach.md` — `replicas` fixo (Dokploy não tem HPA-equivalente), `/k8s` local continua sendo a evidência de escalabilidade real

## Terraform (`workshop-os-infra-kubernetes`)

- [x] `versions.tf` — mesmo provider/versão do Epic 2
- [x] `variables.tf` — `environment_id` (sem default — vem do `terraform output` do Epic 2), `app_image`, `app_replicas` (default 2, ver ADR-006), `app_domain_host` (sem default — domínio real do usuário), limites de CPU/mem reaproveitados de `k8s/deployment.yaml`
- [x] `main.tf` — `dokploy_application.workshop_os` + `dokploy_domain.workshop_os`
- [x] `outputs.tf` — `application_id`, `application_status`, `domain_id`
- [x] README do repo preenchido
- [x] `.tool-versions` + CI alinhados com o Epic 2

## Fechamento do epic

- [x] `terraform init`/`validate` rodados contra o provider real — passou
- [x] Push da branch (sem PR ainda)
- [ ] Apagar este arquivo e a linha em `spec.md` só depois do PR mergeado
