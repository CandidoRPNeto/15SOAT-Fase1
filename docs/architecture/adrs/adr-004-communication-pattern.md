# ADR-004: Padrão de comunicação — REST/JSON síncrono

**Status**: Aceita
**Data**: 2026-09-02

## Contexto

A Fase 3 introduz comunicação entre componentes que antes não existiam
(Lambda ↔ aplicação principal, CI/CD ↔ Dokploy, futura integração Datadog).
Sem um padrão declarado, cada epic tende a decidir isoladamente e gerar
inconsistência entre repositórios.

## Decisão

REST sobre HTTPS com payloads JSON é o padrão default para toda comunicação
síncrona entre componentes da Fase 3 (Lambda → API interna da aplicação
principal, se essa opção for a escolhida em
[RFC-003](../../../backlog.md), CI/CD → APIs do Dokploy). Nenhum broker de
mensagens (SQS, RabbitMQ, Kafka) é introduzido nesta fase — não há
requisito que justifique processamento assíncrono desacoplado além do que
os jobs de fila já existentes (`SendFineNotificationJob`, driver
`database`) cobrem.

## Consequências

- Positivas: um único estilo de integração para depurar e documentar;
  reaproveita o conhecimento já existente no projeto (toda a API atual já
  é REST/JSON); evita introduzir infraestrutura de mensageria sem
  necessidade comprovada.
- Negativas / trade-offs: se um epic futuro precisar de fato de
  processamento assíncrono desacoplado (ex.: alto volume de eventos do
  Lambda), esta decisão precisa ser revisitada e superada por um novo ADR
  — não é uma proibição permanente, é o default até haver motivo concreto
  para mudar.
