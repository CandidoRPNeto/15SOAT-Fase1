# ADR-012: MongoDB no Execution Service

**Status**: Aceita
**Data**: 2026-11-16

## Contexto

O PDF exige pelo menos um banco NoSQL. O Execution Service guarda a tarefa
de execução de cada OS (fase, prioridade, mecânico) e uma timeline de
eventos e notas que só cresce.

## Decisão

MongoDB 7, via `mongodb/laravel-mongodb`:
- coleção `execution_tasks` com um documento por OS e a timeline embutida;
- índice `{status, priority, created_at}` para a fila;
- coleções `outbox_messages` e `processed_messages` para o padrão do ADR-011.

## Consequências

- Positivas: a timeline é lida inteira numa consulta, sem joins; o schema
  flexível aceita notas de diagnóstico livres.
- Negativas: os testes não rodam em SQLite `:memory:`. O CI sobe um service
  container `mongo:7`, e localmente um container Docker.
- Atualização de documento único é atômica no Mongo. A tarefa e o outbox
  ficam no mesmo documento quando é preciso atomicidade, ou usam uma
  transação multi-documento (replica set de 1 nó no kind).
