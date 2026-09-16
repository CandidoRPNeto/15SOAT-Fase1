# ADR-003: Manter `/k8s` e `/infra` (Fase 2) como referência local

**Status**: Aceita
**Data**: 2026-09-02

## Contexto

A Fase 3 adota Dokploy como nuvem primária ([ADR-001](adr-001-dokploy-as-cloud.md)),
o que não usa Kubernetes real. Os diretórios `/k8s` (7 manifests) e
`/infra` (Terraform `infra/cluster` + `infra/database`) da Fase 2 continuam
no repo, validados estaticamente e testados contra um cluster `kind` local.
Pergunta: remover (já que não são mais o alvo de deploy) ou manter?

## Decisão

Manter `/k8s` e `/infra` exatamente como entregues na Fase 2, reenquadrados
via `k8s/README.md` e uma nota no `README.md` raiz como "implementação
Kubernetes literal, referência local" — não o alvo de deploy em nuvem da
Fase 3.

## Consequências

- Positivas: preserva evidência já validada (9/9 manifests contra o schema
  K8s 1.29, `terraform fmt`/`init`/`validate`/`plan` OK) sem custo de
  manutenção contínua (YAML/HCL estático, não roda em CI); é a mitigação
  mais forte contra o risco descrito em ADR-001 — se a avaliação exigir
  Kubernetes real, há uma implementação de fato no mesmo repositório; os
  estágios `deploy-k8s`/`deploy-db` do `ci-cd.yml` já são condicionais ao
  secret `KUBE_CONFIG`, então convivem sem conflito com o novo alvo Dokploy.
- Negativas / trade-offs: o repo carrega dois caminhos de deploy
  documentados (kind local vs. Dokploy cloud) — precisa deixar claro em
  README qual é o vigente para não confundir quem lê o repo pela primeira
  vez.
