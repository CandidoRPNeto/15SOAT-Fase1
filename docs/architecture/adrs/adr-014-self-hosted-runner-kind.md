# ADR-014: Deploy automatizado via runner self-hosted no kind local

**Status**: Aceita
**Data**: 2026-11-16

## Contexto

O PDF exige deploy automatizado em Kubernetes para cada serviço. O único
cluster disponível é o kind `workshop-os` desta máquina (a Fase 3 rodou toda
local). Um runner hospedado pelo GitHub não alcança o cluster.

## Decisão

Cada repo de serviço registra um GitHub Actions **self-hosted runner**
(label `kind`) nesta máquina. Os jobs de teste, qualidade e build rodam em
`ubuntu-latest`. Só o job `deploy` roda no runner, e somente em push na
branch protegida. Ele faz:
1. `docker pull` da imagem do GHCR;
2. `kind load`;
3. `kubectl apply -k k8s/`;
4. Job de migrate;
5. `rollout status`;
6. smoke test via Traefik.

## Consequências

- Positivas: deploy real e observável; nenhum kubeconfig sai da máquina.
- Negativas: o deploy só acontece com a máquina ligada.
- Segurança: um runner self-hosted em repo público pode executar código de
  PRs de fork. Mitigações:
  - o job de deploy só roda em `push` na branch protegida;
  - ativar "Require approval for all outside collaborators";
  - o runner roda com um usuário sem sudo.
