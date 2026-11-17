# ADR-010: Saga orquestrada no OS Service

**Status**: Aceita
**Data**: 2026-11-16

## Contexto

Abrir OS → diagnóstico → orçamento → aprovação → reparo → pagamento atravessa
3 bancos. Não há transação distribuída (2PC) entre Postgres e MongoDB, e o
PDF exige rollback/compensação em qualquer etapa.

## Decisão

Saga **orquestrada**. O orquestrador (`ServiceOrderSaga`) vive no OS Service,
persiste o estado em `saga_instances`/`saga_steps` e conversa com Billing e
Execution por comandos e respostas no RabbitMQ.

Passos (C = compensável, P = pivot, R = retentável):

| # | Passo | Dono | Tipo | Compensação |
|---|---|---|---|---|
| 1 | Abrir OS + enfileirar diagnóstico | OS → Execution | C | cancelar tarefa, OS `cancelled` |
| 2 | Diagnóstico concluído → criar orçamento | Execution → OS → Billing | C | anular orçamento |
| 3 | Aprovação → reservar estoque + enfileirar reparo | Billing → OS → Execution | C | liberar estoque, anular orçamento, cancelar tarefa, OS `cancelled` |
| 4 | Início do reparo | Execution | P | — (trabalho físico começou) |
| 5 | Fim do reparo → solicitar pagamento | Execution → OS → Billing | R | retry |
| 6 | Pagamento confirmado (polling Mercado Pago) | Billing → OS | R | retry; sem compensação |

Um watchdog (`saga:watchdog`, a cada minuto) reenvia passos sem resposta
(até 3 vezes) e, depois disso, dispara a compensação.

## Por que orquestrada e não coreografada

- O fluxo é linear e tem um dono natural (a OS). Um coordenador explícito
  mostra o estado atual da saga numa consulta (`GET /service-orders/{id}/history`).
- A compensação fica num lugar só. Na coreografia, cada serviço precisaria
  conhecer as falhas dos outros para compensar.
- É testável ponta a ponta com BDD no OS Service, com fakes das respostas.
- O custo é acoplamento do OS Service aos comandos dos outros dois. Ele é
  aceitável porque os contratos são versionados (AsyncAPI no repo produtor).

## Consequências

- Positivas: rastreabilidade; compensação determinística; o watchdog cobre
  serviço fora do ar.
- Negativas: o OS Service é o ponto central do fluxo. Se ele cair, a saga
  pausa (não corrompe: o estado é persistido e as mensagens ficam no broker).
- Ressalva: a partir do pivot (início do reparo) não há rollback, só retry.
  Isso é intencional, porque a peça já foi usada.
