# Epic 1 — Multi-repo foundation + branch protection

**Depende de**: [`evolucao_fase3`](evolucao_fase3), [`backlog.md`](backlog.md) (epics 2-7, na fila depois deste).
**Objetivo**: preparar a base estrutural da Fase 3 — decisões arquiteturais registradas (RFC/ADR), os 4 repositórios existindo com branch protection e CI/CD skeleton, antes de qualquer epic de infra/código começar.

## Provisionamento (feito antes deste arquivo existir)

- [x] `evolucao_fase3` criado na raiz
- [x] `spec.md`, `backlog.md`, `rules.md`, `history.md` criados (convenção flat)

## Decisões arquiteturais

- [x] `docs/architecture/rfcs/TEMPLATE.md`
- [x] `docs/architecture/adrs/TEMPLATE.md`
- [x] `docs/architecture/rfcs/rfc-001-cloud-strategy.md`
- [x] `docs/architecture/adrs/adr-001-dokploy-as-cloud.md`
- [x] `docs/architecture/adrs/adr-002-four-repo-split.md`
- [x] `docs/architecture/adrs/adr-003-keep-local-k8s-reference.md`
- [x] `docs/architecture/adrs/adr-004-communication-pattern.md`

## Repositórios GitHub

- [x] `gh repo create workshop-os-lambda-auth --public`
- [x] `gh repo create workshop-os-infra-kubernetes --public`
- [x] `gh repo create workshop-os-infra-database --public`

## Branch protection (4 repos)

- [x] `15SOAT-Fase1`: `master` protegida (PR obrigatório, check `test` obrigatório, sem force-push/delete, `enforce_admins`)
- [x] `workshop-os-lambda-auth`: `main` + `homolog` protegidas (check `build`)
- [x] `workshop-os-infra-kubernetes`: `main` + `homolog` protegidas (check `terraform-validate`)
- [x] `workshop-os-infra-database`: `main` + `homolog` protegidas (check `terraform-validate`)
- [x] Regra aplicada: PR obrigatório, CI skeleton obrigatório, sem force-push, sem commit direto (inclusive admin — `enforce_admins=true`)
- [x] Sequenciamento respeitado: workflow rodou 1x sem proteção antes da regra ser aplicada

## CI/CD skeleton + README por repo novo

- [x] `workshop-os-lambda-auth`: CI skeleton (job `build`, placeholder) + README com seções placeholder
- [x] `workshop-os-infra-kubernetes`: CI skeleton (`terraform fmt -check` + `validate`) + README placeholder
- [x] `workshop-os-infra-database`: CI skeleton (`terraform fmt -check` + `validate`) + README placeholder
- [x] `15SOAT-Fase1`: seção "Fase 3 (em andamento)" no README raiz, apontando para `spec.md`/`backlog.md` e os 3 repos novos

## `/k8s` e `/infra`

- [x] `k8s/README.md` — reframe como referência K8s local, não alvo cloud da Fase 3
- [x] Linha equivalente na seção K8s do `README.md` raiz

## Fechamento do epic

- [x] Verificar branch protection ativa nos 4 repos (ambas branches onde aplicável)
- [x] Verificar CI skeleton verde em cada repo novo
- [x] Verificar README não vazio em cada repo novo
- [ ] **Pendente**: mergear [PR #1](https://github.com/CandidoRPNeto/15SOAT-Fase1/pull/1) em `master` (bloqueado pelo classifier de auto-mode do Claude Code — precisa ser feito manualmente pelo usuário ou autorizado explicitamente). Só depois disso apagar este arquivo e remover sua linha da fila em `spec.md`.
