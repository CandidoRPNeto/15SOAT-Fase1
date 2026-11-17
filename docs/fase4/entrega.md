# Fase 4 — material para o PDF de entrega

Conteúdo de apoio para o PDF (o PDF em si é montado à parte).

## Repositórios

| Papel | Repositório |
|---|---|
| OS Service (orquestrador da saga) | https://github.com/CandidoRPNeto/15SOAT-Fase1 |
| Billing Service | https://github.com/CandidoRPNeto/15SOAT-Fase1-billing |
| Execution Service | https://github.com/CandidoRPNeto/15SOAT-Fase1-execution |
| Plataforma (Traefik, RabbitMQ, Jaeger, scripts de deploy/demo) | https://github.com/CandidoRPNeto/15SOAT-Fase1-kubernetes |
| Auth por CPF (Fase 3, reaproveitado) | https://github.com/CandidoRPNeto/15SOAT-Fase1-lambda |

## Diagramas

Fontes Mermaid em `docs/architecture/diagrams/fase4/` (SVG e PNG ao lado). `scripts/render-diagrams.sh` gera as imagens e copia os PNG, numerados na ordem do PDF, para `diagramas/` (pasta local, fora do git).

| PNG em `diagramas/` | Fonte | Uso no PDF |
|---|---|---|
| `01-arquitetura-geral` | `architecture-overview` | Diagrama geral: microsserviços, bancos e comunicação (obrigatório) |
| `02-saga-fluxo-feliz` | `saga-happy-path` | Estratégia da saga: sequência no fluxo completo (obrigatório) |
| `03-saga-compensacao` | `saga-compensation` | Estratégia da saga: falhas e compensação (estoque, fila lotada, serviço fora do ar) |
| `04-saga-maquina-de-estados` | `saga-state-machine` | Estados da OS conduzidos pela saga |
| `05-rastreamento-distribuido` | `distributed-tracing` | Observabilidade: trace atravessando outbox e RabbitMQ até o Jaeger (ADR-016) |
| `06-pipeline-ci-cd` | `cicd-pipeline` | Pipeline independente por serviço |
| `07-implantacao-kubernetes` | `k8s-deployment` | Implantação no Kubernetes (namespaces e workloads) |

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
- **RabbitMQ**: filas duráveis, DLQ, topic routing; o contexto do trace viaja nos headers da mensagem.
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
- **Observabilidade** ([ADR-016](../architecture/adrs/adr-016-local-apm-jaeger.md)):
  dd-trace da Fase 3 nas 3 imagens, agora enviando a um OpenTelemetry Collector
  que repassa ao Jaeger (o plano free do Datadog não tem APM). Um único trace
  cobre gateway → OS → outbox → RabbitMQ → Billing/Execution. O outbox guarda o
  contexto do trace junto com a mensagem. Logs JSON com `correlation_id`/`saga_id`
  e filas/DLQ na UI do RabbitMQ completam o monitoramento.

## Testes e qualidade

| Serviço | Testes (CI) | Cobertura (CI) | Execução | BDD |
|---|---|---|---|---|
| OS | 170 (unit + feature + BDD) | **94,6%** | [run 44142008421](https://github.com/CandidoRPNeto/15SOAT-Fase1/actions/runs/44142008421) | `features/saga_ordem_de_servico.feature` (5 cenários) |
| Billing | 50 | **99,3%** | [run 44128010236](https://github.com/CandidoRPNeto/15SOAT-Fase1-billing/actions/runs/44128010236) | — |
| Execution | 33 (MongoDB real) | **99,0%** | [run 44128027026](https://github.com/CandidoRPNeto/15SOAT-Fase1-execution/actions/runs/44128027026) | — |

No CI os testes rodam contra RabbitMQ real (PostgreSQL/MongoDB também no Execution); o gate é `--min=80`. Local, sem broker: ~89% / ~85% / ~81%.

Deploy automático validado: os runs do Billing e do Execution acima concluíram o job `deploy` no runner self-hosted (kind). Os Deployments rodam a imagem `ghcr.io/candidorpneto/15soat-fase1-<svc>:<sha do commit>`.
