# ADR-006: Abordagem de escalabilidade — réplicas fixas, sem HPA

**Status**: Aceita
**Data**: 2026-09-02

## Contexto

[ADR-001](adr-001-dokploy-as-cloud.md) já registrou a ressalva: Docker
Swarm/Dokploy não tem autoscaling automático por CPU/memória nativo
equivalente a um HPA do Kubernetes. Confirmado agora contra o schema real
do provider ([ADR-005](adr-005-dokploy-terraform-provider.md)): o recurso
`dokploy_application` expõe só `replicas` (Number) — um número fixo de
réplicas, sem `min`/`max`/target de utilização. Não há meio-termo — ou o
número é fixo, ou não é gerido por este provider.

## Decisão

O recurso de app do Epic 3 (`workshop-os-infra-kubernetes`) usa
`replicas = 2` como baseline fixo — mesmo valor do `minReplicas` do
[`k8s/hpa.yaml`](../../../k8s/hpa.yaml) da Fase 2, para manter o mesmo piso
de disponibilidade (2 réplicas, não 1) mesmo sem autoscaling real. O
requisito de "escalabilidade dinâmica" da Fase 3 continua demonstrado pela
implementação Kubernetes literal mantida em `/k8s`
([ADR-003](adr-003-keep-local-k8s-reference.md)): HPA real, 2 a 6 réplicas,
CPU 70% / memória 80%, testado localmente via `kind`.

## Consequências

- Positivas: comportamento prático e testável (número fixo, sem
  comportamento condicional a depurar); consistente com o baseline já
  validado na Fase 2.
- Negativas / trade-offs: nenhuma resposta automática a pico de carga no
  ambiente Dokploy/cloud — só no ambiente `kind` local. Se a avaliação da
  Fase 3 exigir escalar sob carga *no ambiente cloud especificamente*, esta
  decisão não atende; mitigação seria escalar manualmente
  (`replicas` via `terraform apply` ou pela UI do Dokploy) durante uma
  demonstração, não automaticamente.
- Ressalva conhecida: se o Dokploy ganhar autoscaling nativo em versão
  futura (ver pergunta em aberto do [RFC-001](../rfcs/rfc-001-cloud-strategy.md)),
  esta ADR precisa ser revisitada — não é uma limitação permanente do
  projeto, é uma limitação do provider/versão atual do Dokploy.
