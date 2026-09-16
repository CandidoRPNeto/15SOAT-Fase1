# ADR-002: Split em 4 repositórios

**Status**: Aceita
**Data**: 2026-09-02

## Contexto

`evolucao_fase3` exige explicitamente 4 repositórios separados, cada um com
CI/CD e deploy automático próprios: Lambda, infra Kubernetes, infra de
banco gerenciado, e a aplicação principal.

## Decisão

| # | Repo | Conteúdo | Branch padrão |
|---|------|----------|-----------------|
| 1 | `workshop-os-lambda-auth` | AWS Lambda (valida CPF, consulta cliente, emite JWT), CI/CD próprio, README com URL do API Gateway | `main` |
| 2 | `workshop-os-infra-kubernetes` | Terraform (provider Dokploy) — recurso de app, domínio/roteamento, deploy webhook | `main` |
| 3 | `workshop-os-infra-database` | Terraform (provider Dokploy) — instância Postgres gerenciada | `main` |
| 4 | `15SOAT-Fase1` (este repo, sem rename) | Laravel app, Dockerfile, docker-compose, `/k8s`+`/infra` mantidos como referência local, CI/CD passa a também deployar no Dokploy | `master` |

O repo 4 reaproveita o repositório existente (histórico de Fase 1/2 já
compartilhado com `soat-architecture`) em vez de criar um repo novo — ver
justificativa em `history.md`. Os 3 repos novos usam o prefixo
`workshop-os-` (nome do produto, já usado em `CLAUDE.md`/README), não um
prefixo `15soat-fase3-` que perde sentido após o curso. Todos criados
públicos, consistente com o repo 4 (reversível a qualquer momento).

## Consequências

- Positivas: atende o requisito de 4 repos separados; nomes autoexplicativos
  e estáveis fora do contexto acadêmico; preserva histórico já entregue.
- Negativas / trade-offs: `master` (repo 4) e `main` (repos novos)
  convivem sem padronização — flagged em cada README, não silenciosamente
  ignorado. Mudanças cross-repo (ex.: claims do JWT entre Lambda e app)
  exigem coordenar PRs em repositórios diferentes.
