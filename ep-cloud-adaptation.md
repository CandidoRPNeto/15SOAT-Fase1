# Epic 4 — App cloud adaptation

**Depende de**: Epic 1, Epic 2, Epic 3.
**Repos de código**: `15SOAT-Fase1` (esta app), `15SOAT-Fase1-kubernetes`, `15SOAT-Fase1-database` (workflows `deploy.yml`).
**Objetivo**: app existente (sem feature nova) preparada pra rodar no Dokploy — logs JSON + correlation id, trusted proxy, CI/CD disparando o deploy nos repos de infra.

**Nota de sequenciamento**: branch empilhada
(`feature/2026/09/epic4-cloud-adaptation` a partir de
`feature/2026/09/epic3-app-infra-adrs`), mesma lógica dos epics anteriores.

## App (`15SOAT-Fase1`)

- [x] `app/Http/Middleware/AssignCorrelationId.php` + testes unitário e de feature
- [x] Registrado globalmente em `bootstrap/app.php` (`prepend`, roda antes de tudo)
- [x] `trustProxies(at: '*')` em `bootstrap/app.php` — atrás do Traefik do Dokploy
- [x] Canal de log `json` (Monolog `JsonFormatter`, stderr) em `config/logging.php`
- [x] `.env.example` documentando `LOG_CHANNEL=json` pra cloud
- [x] Suíte completa rodada (134 testes, era 131 — 3 novos, todos verdes)

## CI/CD — deploy pro Dokploy

- [x] `homolog` criada e protegida neste repo (paridade com os outros 3 — evolucao_fase3 pede deploy automático de homologação **e** produção)
- [x] `.github/workflows/ci-cd.yml`: trigger em `homolog` também; novo stage `deploy-dokploy` disparando `15SOAT-Fase1-kubernetes` via `workflow_dispatch` (gated por `DOKPLOY_DISPATCH_TOKEN`)
- [x] `15SOAT-Fase1-kubernetes/.github/workflows/deploy.yml` — recebe o dispatch, `terraform apply` gated por 4 secrets
- [x] `15SOAT-Fase1-database/.github/workflows/deploy.yml` — apply-on-push em `main`, gated por 3 secrets
- [x] `actionlint` limpo nos 3 workflows

## Lacunas documentadas (não resolvidas silenciosamente)

- [x] RFC-002 atualizada: nenhum backend Terraform remoto — state local não sobrevive entre runs de CI efêmero. Sugestão (não decidida): backend `pg` contra o próprio Postgres do Epic 2.
- [x] `15SOAT-Fase1-kubernetes` só modela o ambiente `production` — dispatch de `homolog` roda mas aplica no mesmo recurso único.

## Fechamento do epic

- [x] Push das 3 branches (`15SOAT-Fase1`, `15SOAT-Fase1-kubernetes`, `15SOAT-Fase1-database`), sem PR ainda
- [ ] Apagar este arquivo e a linha em `spec.md` só depois dos PRs mergeados
