# RFC-001: Estratégia de nuvem para a Fase 3

**Status**: Aceita
**Data**: 2026-09-02
**Autor(es)**: Candido (decisão), registrado com apoio de IA

## Contexto

[`evolucao_fase3`](../../../evolucao_fase3) exige, entre outros pontos: API
Gateway, Function Serverless para autenticação via CPF, banco de dados
gerenciado, cluster Kubernetes com escalabilidade, e observabilidade via
Datadog — tudo com deploy automático para a nuvem. A Fase 2 nunca chegou a
rodar em nuvem real (só `kind` local, ver [`history.md`](../../../history.md)).
É preciso escolher **qual(is) provedor(es) de nuvem** hospedam cada peça.

## Opções consideradas

1. **Tudo em uma nuvem pública tradicional (AWS/GCP/Azure) com serviços
   gerenciados nativos** (EKS/GKE/AKS, RDS/Cloud SQL, API Gateway nativo,
   Lambda/Cloud Functions). Mais aderente à letra do requisito
   ("Kubernetes", "Function Serverless", "Banco Gerenciado" todos com
   produtos dedicados), mas exige gerir custos, quotas e configuração de
   3+ serviços gerenciados distintos — overhead alto para um projeto
   acadêmico de uma pessoa.
2. **Dokploy (PaaS self-hosted) como a nuvem inteira**, rodando numa única
   VPS: hospeda Postgres (via seu recurso "Database"), o Gateway (Traefik
   embutido) e os serviços da aplicação. O requisito de "Cluster Kubernetes
   com escalabilidade" é satisfeito pelo orquestrador do próprio Dokploy
   (Docker Swarm/Compose por baixo dos panos), não por um cluster K8s real.
   Menor custo e superfície operacional; risco de leitura literal do
   requisito "Kubernetes" não ser satisfeita ao pé da letra (ver
   [ADR-001](../adrs/adr-001-dokploy-as-cloud.md), ressalva de HPA).
3. **Híbrido: Dokploy para a maior parte + AWS Lambda real só para a
   function serverless.** Combina o custo/operação baixos da opção 2 com
   serverless de verdade especificamente onde o requisito pede
   explicitamente "Function Serverless" — sem levar o projeto inteiro para
   uma segunda nuvem paga.

## Recomendação

**Opção 3 (híbrido)** — decisão já tomada pelo usuário, registrada aqui
formalmente:

- **Dokploy** hospeda banco (Postgres gerenciado via Dokploy), API Gateway
  (Traefik) e a aplicação principal. Ver [ADR-001](../adrs/adr-001-dokploy-as-cloud.md).
- **AWS Lambda (free tier)** hospeda exclusivamente a function serverless
  de auth por CPF — a única peça numa segunda nuvem, porque "serverless de
  verdade" é o que esse requisito específico exige, e nenhum recurso do
  Dokploy é serverless real.
- **Datadog** cobre observabilidade cross-cloud (conta/trial já existente,
  sem passo de setup).

Justificativa: minimiza custo e complexidade operacional (uma VPS Dokploy
em vez de gerir 3+ serviços gerenciados AWS/GCP/Azure), mantendo serverless
real onde é explicitamente cobrado, sem abrir mão do requisito.

## Consequências / próximos passos

- [ADR-001](../adrs/adr-001-dokploy-as-cloud.md) torna a escolha do Dokploy permanente, com a ressalva de escalabilidade.
- [ADR-002](../adrs/adr-002-four-repo-split.md) mapeia essa estratégia para os 4 repositórios exigidos.
- Epic 2/3 (`backlog.md`) provisionam banco e app no Dokploy via Terraform.
- Epic 5 (`backlog.md`) implementa o Lambda na AWS e a integração com o Gateway/Laravel.
- Epic 6 (`backlog.md`) cobre observabilidade cross-cloud (Datadog Agent na VPS + extensão/layer no Lambda).

## Perguntas em aberto

- Confirmar o roadmap atual do Dokploy (autoscaling nativo, suporte a K8s)
  antes do Epic 3 — o conhecimento usado para esta RFC pode estar
  desatualizado, já que é uma ferramenta em desenvolvimento ativo.
