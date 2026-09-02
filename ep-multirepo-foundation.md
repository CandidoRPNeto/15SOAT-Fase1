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

Ação outward-facing — confirmar com o usuário antes de rodar.

- [ ] `gh repo create workshop-os-lambda-auth --public`
- [ ] `gh repo create workshop-os-infra-kubernetes --public`
- [ ] `gh repo create workshop-os-infra-database --public`

## Branch protection (4 repos)

- [ ] `15SOAT-Fase1`: proteger `master`
- [ ] `workshop-os-lambda-auth`: branch `main` + `homolog`, ambas protegidas
- [ ] `workshop-os-infra-kubernetes`: branch `main` + `homolog`, ambas protegidas
- [ ] `workshop-os-infra-database`: branch `main` + `homolog`, ambas protegidas
- [ ] Regra: exigir PR antes de merge, exigir CI skeleton passando, sem force-push, sem commit direto
- [ ] **Sequenciamento**: subir o workflow skeleton sem proteção primeiro, deixar rodar 1x, só então aplicar a regra (status check obrigatório exige um check que já rodou)

## CI/CD skeleton + README por repo novo

- [ ] `workshop-os-lambda-auth`: CI skeleton (lint/test placeholder) + README com seções placeholder
- [ ] `workshop-os-infra-kubernetes`: CI skeleton (`terraform fmt -check` + `validate`) + README placeholder
- [ ] `workshop-os-infra-database`: CI skeleton (`terraform fmt -check` + `validate`) + README placeholder
- [x] `15SOAT-Fase1`: seção "Fase 3 (em andamento)" no README raiz, apontando para `spec.md`/`backlog.md` e os 3 repos novos

## `/k8s` e `/infra`

- [x] `k8s/README.md` — reframe como referência K8s local, não alvo cloud da Fase 3
- [x] Linha equivalente na seção K8s do `README.md` raiz

## Fechamento do epic

- [ ] Verificar branch protection ativa nos 4 repos (ambas branches onde aplicável)
- [ ] Verificar CI skeleton verde em cada repo novo
- [ ] Verificar README não vazio em cada repo novo
- [ ] Apagar este arquivo e remover sua linha da fila em `spec.md`
