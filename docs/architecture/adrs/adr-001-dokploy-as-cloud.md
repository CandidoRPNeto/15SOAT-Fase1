# ADR-001: Dokploy como plataforma de nuvem primária

**Status**: Aceita
**Data**: 2026-09-02

## Contexto

`evolucao_fase3` exige "Cluster Kubernetes com escalabilidade" e "Banco de
Dados Gerenciado (PostgreSQL)" provisionados em nuvem via Terraform. Um
cluster K8s gerenciado real (EKS/GKE/AKS ou até k3s auto-hospedado) tem
custo e complexidade operacional desproporcionais ao escopo de um projeto
acadêmico de uma pessoa (ver [RFC-001](../rfcs/rfc-001-cloud-strategy.md)).

## Decisão

Dokploy (PaaS self-hosted, rodando numa única VPS) é a plataforma de nuvem
primária do projeto. Hospeda o banco (recurso "Database" do Dokploy), o
Gateway (Traefik embutido) e a aplicação principal. O orquestrador interno
do Dokploy (Docker Swarm/Compose) satisfaz o requisito de "cluster com
escalabilidade" — **não é provisionado um cluster Kubernetes real em
nuvem**.

## Consequências

- Positivas: custo e superfície operacional muito menores que gerir um
  cluster K8s gerenciado; um único ponto de operação (a VPS Dokploy) para
  banco, gateway e app.
- Negativas / trade-offs: Docker Swarm não tem autoscaling automático por
  CPU/memória nativo equivalente a um HPA do Kubernetes — o requisito
  "escalabilidade" é satisfeito de forma mais fraca que num K8s real.
- Ressalva conhecida (risco aceito conscientemente): se a avaliação da
  Fase 3 interpretar "Cluster Kubernetes" ao pé da letra, essa decisão
  não atende literalmente. Mitigação: `/k8s` e `/infra` da Fase 2
  permanecem no repo como implementação K8s real e testável, ver
  [ADR-003](adr-003-keep-local-k8s-reference.md). Vale reconfirmar o
  roadmap do Dokploy (pode ter ganhado autoscaling/K8s desde que esta
  decisão foi registrada) antes de fechar o Epic 3.

## Limitações frente ao enunciado

O Dokploy numa VPS não equivale literalmente aos dois recursos pedidos:

| Requisito | O que o Dokploy entrega | O que falta frente a um serviço gerenciado |
|---|---|---|
| Banco de dados gerenciado | Container PostgreSQL na mesma VPS, provisionado como recurso `Database` pelo Terraform | Alta disponibilidade e failover automáticos, backup/restore point-in-time gerenciado, patching do motor pelo provedor, escala vertical sem downtime, isolamento de hardware em relação à aplicação |
| Cluster Kubernetes com escalabilidade | Docker Swarm/Compose de um único nó | API Kubernetes (manifests, HPA, probes, RBAC), autoscaling por métrica, múltiplos nós, rescheduling em caso de falha do nó |

- Risco operacional: a VPS é ponto único de falha para banco, gateway e aplicação ao mesmo tempo.
- Mitigação na Fase 3: os manifests Kubernetes da Fase 2 (`/k8s`, `/infra`) continuaram no repositório como implementação de referência testável no kind ([ADR-003](adr-003-keep-local-k8s-reference.md)).
- Desdobramento na Fase 4: a execução passou a ser em Kubernetes real (cluster kind), com namespaces, HPA, probes e um banco próprio por serviço em StatefulSet ([RFC-004](../rfcs/rfc-004-microservices-split.md), [ADR-014](adr-014-self-hosted-runner-kind.md)). O kind é local e de nó único para a camada de controle, então ainda não é um cluster gerenciado em nuvem; essa é a limitação que permanece.

