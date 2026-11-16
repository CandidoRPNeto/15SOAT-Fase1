# RFC-004: Divisão em microsserviços

**Status**: Aceita
**Data**: 2026-11-16
**Autor(es)**: CandidoRPNeto

## Contexto

[`evolucao_fase4`](../../../evolucao_fase4) exige ao menos 3 microsserviços,
cada um com repositório, infraestrutura e banco próprios, com pelo menos um
banco SQL e um NoSQL, e nenhum serviço lendo o banco de outro. O monólito da
Fase 3 concentra atendimento, diagnóstico, orçamento, pagamento e execução
num único `ServiceOrderController` e num único Postgres.

## Opções consideradas

1. **Divisão sugerida pelo PDF (OS / Billing / Execution)**: segue as áreas do
   negócio (atendimento, cobrança, oficina). Cada área tem dono claro de dado
   e ciclo de mudança próprio. Contra: o catálogo (serviços/peças/estoque)
   precisa ficar em algum lugar — fica no OS Service.
2. **Divisão técnica (Auth / Catálogo / OS / Pagamento / Execução)**: 5
   serviços, mais granular. Contra: o dobro de pipelines, bancos e
   manifestos sem ganho real para o volume de uma oficina; o catálogo e a
   OS mudam juntos.
3. **Repos novos do zero para os 3 serviços**: mantém o monólito congelado.
   Contra: duplica auth/cadastros que já funcionam e estão testados.

## Recomendação

Opção 1, reaproveitando o repo atual como OS Service (decisão do usuário):

| Serviço | Repo | Banco | Por quê esse banco |
|---|---|---|---|
| OS Service | `15SOAT-Fase1` | PostgreSQL | dados relacionais (cliente→veículo→OS→itens), estoque com reserva transacional, estado da saga |
| Billing Service | `15SOAT-Fase1-billing` | PostgreSQL | dinheiro exige ACID e constraints (um orçamento ativo por OS, um pagamento aprovado por orçamento) |
| Execution Service | `15SOAT-Fase1-execution` | MongoDB | tarefa de execução + timeline de eventos/notas é um documento por OS, append-only, sem joins; fila consultada por prioridade |

Auth, clientes, veículos e catálogo ficam no OS Service: são consultados em
toda abertura de OS e mudam junto com ela. O Lambda de CPF (Fase 3) continua
consultando o OS Service via `/internal/clients/cpf-lookup`.

## Consequências / próximos passos

- Rotas de orçamento/aprovação/pagamento saem do OS Service para o Billing;
  início/finalização de diagnóstico e reparo vão para o Execution.
- Consistência entre os 3 bancos via Saga orquestrada — [ADR-010](../adrs/adr-010-saga-orchestration.md).
- Mensageria — [ADR-011](../adrs/adr-011-rabbitmq-async-messaging.md); MongoDB — [ADR-012](../adrs/adr-012-mongodb-execution-store.md).
- Identidade propagada pelo gateway — [ADR-013](../adrs/adr-013-gateway-forward-auth.md).

## Perguntas em aberto

Nenhuma.
