# Fase 4 — material para o PDF de entrega

Conteúdo de apoio para o PDF (o PDF em si é montado à parte).

## Repositórios

| Papel | Repositório |
|---|---|
| OS Service (orquestrador da saga) | https://github.com/CandidoRPNeto/15SOAT-Fase1 |
| Billing Service | https://github.com/CandidoRPNeto/15SOAT-Fase1-billing |
| Execution Service | https://github.com/CandidoRPNeto/15SOAT-Fase1-execution |
| Plataforma (Traefik, RabbitMQ, scripts de deploy/demo) | https://github.com/CandidoRPNeto/15SOAT-Fase1-kubernetes |
| Auth por CPF (Fase 3, reaproveitado) | https://github.com/CandidoRPNeto/15SOAT-Fase1-lambda |

## Diagramas (`docs/architecture/diagrams/fase4/`, SVG e PNG)

| Arquivo | Uso no PDF |
|---|---|
| `architecture-overview` | Diagrama geral: microsserviços, bancos e comunicação (obrigatório) |
| `saga-happy-path` | Sequência da saga no fluxo completo |
| `saga-compensation` | Falhas e compensação (estoque, fila lotada, serviço fora do ar) |
| `saga-state-machine` | Estados da OS conduzidos pela saga |
| `cicd-pipeline` | Pipeline independente por serviço |
| `k8s-deployment` | Implantação no Kubernetes (namespaces e workloads) |

Regerar: `scripts/render-diagrams.sh`.

## Estratégia do Saga Pattern — texto-base

Saga **orquestrada**, com o orquestrador dentro do OS Service
([ADR-010](../architecture/adrs/adr-010-saga-orchestration.md)). O fluxo
abrir OS → diagnóstico → orçamento → aprovação → reparo → pagamento tem um
dono natural (a OS), e um coordenador explícito permite consultar o estado
da saga num único lugar (`GET /api/v1/service-orders/{id}/history`).
Também concentra a compensação e permite testar o fluxo inteiro com BDD.

- **Comunicação**: comandos `cmd.<serviço>.<verbo>` e respostas
  `evt.<serviço>.<fato>` numa exchange topic do RabbitMQ
  ([ADR-011](../architecture/adrs/adr-011-rabbitmq-async-messaging.md)).
- **Consistência**:
  - *transactional outbox* — o dado e a mensagem são gravados na mesma transação;
  - *inbox idempotente* — cada `message_id` é processado uma única vez.
- **Compensação**: cada passo compensável é desfeito em ordem reversa:
  - reserva de estoque liberada;
  - orçamento anulado (`cmd.billing.void_budget`);
  - tarefa cancelada (`cmd.execution.cancel_task`);
  - OS cancelada.
- **Pivot**: o início do reparo. A partir dele só há retry (a peça já foi usada).
- **Resiliência**: o `saga:watchdog` reenvia passos sem resposta (3×) e então
  compensa. As filas são duráveis, e falhas de processamento vão para a DLQ.
- **Por que não coreografada**: na coreografia, cada serviço precisaria conhecer as
  falhas dos outros para compensar. O fluxo ficaria espalhado em 3 repositórios
  e seria mais difícil de rastrear e demonstrar.

## Justificativa da divisão e das tecnologias — texto-base

- **Divisão** ([RFC-004](../architecture/rfcs/rfc-004-microservices-split.md)):
  segue as áreas do negócio. Cada serviço tem dono claro de dado e ciclo de mudança próprio:
  - atendimento/OS;
  - cobrança;
  - oficina/execução.
  Cadastros e catálogo ficam no OS Service porque mudam junto com a OS.
- **PostgreSQL** (OS e Billing): dados relacionais e transações ACID (estoque,
  dinheiro, estado da saga).
- **MongoDB** (Execution, [ADR-012](../architecture/adrs/adr-012-mongodb-execution-store.md)):
  uma tarefa por OS com timeline embutida, append-only, sem joins.
- **RabbitMQ**: filas duráveis, DLQ, topic routing e propagação de trace do Datadog.
- **Traefik + ForwardAuth** ([ADR-013](../architecture/adrs/adr-013-gateway-forward-auth.md)):
  o login fica num lugar só, e os serviços novos não dependem de Sanctum.
- **Mercado Pago** (Checkout Pro, credenciais de teste): a confirmação é feita por
  polling da API, porque o cluster local não tem URL pública para webhook.
- **Kubernetes (kind) + runner self-hosted**
  ([ADR-014](../architecture/adrs/adr-014-self-hosted-runner-kind.md)): deploy
  automatizado e observável a cada merge.
- **Laravel 13 nos 3 serviços**: reaproveita padrões e testes. O código de mensageria é
  copiado por repositório para manter a autonomia de deploy
  ([ADR-015](../architecture/adrs/adr-015-per-repo-messaging-lib.md)).
- **Observabilidade**: Datadog da Fase 3 (APM com trace distribuído via AMQP,
  logs JSON com `correlation_id`/`saga_id`, monitores).

## Testes e qualidade

| Serviço | Testes | Cobertura (local, sem broker) | BDD |
|---|---|---|---|
| OS | PHPUnit (unit + feature) | ~89% | `features/saga_ordem_de_servico.feature` (5 cenários) |
| Billing | PHPUnit | ~85% | — |
| Execution | PHPUnit (MongoDB real) | ~81% | — |

No CI, os testes rodam também contra RabbitMQ real (cobertura maior). O gate é `--min=80`.
