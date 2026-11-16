# ADR-011: RabbitMQ para comunicação assíncrona, com outbox e inbox

**Status**: Aceita (supera [ADR-004](adr-004-communication-pattern.md))
**Data**: 2026-11-16

## Contexto

O ADR-004 adotou só REST e deixou explícito que deveria ser revisto quando
surgisse processamento assíncrono desacoplado. A Fase 4 exige mensageria
para a saga entre 3 serviços.

## Decisão

- **Broker**: RabbitMQ no namespace `platform` do kind.
- **Topologia**:
  - exchange `workshop.commands` (direct): uma fila por serviço (`os.inbox`, `billing.inbox`, `execution.inbox`);
  - exchange `workshop.events` (topic): respostas e eventos;
  - DLQ por fila.
- **Envelope**: `message_id`, `type`, `saga_id`, `correlation_id`, `occurred_at`, `payload`.
- **Transactional outbox**: o serviço grava a mensagem na mesma transação do
  dado de negócio, e `outbox:relay` publica em seguida. Isso evita "gravou e
  não publicou".
- **Inbox idempotente**: `processed_messages` (chave `message_id`) descarta
  reentregas. A entrega é at-least-once.
- **REST síncrono continua** para leitura e comandos de usuário
  (cliente → gateway → serviço).
- **Biblioteca**: `php-amqplib` atrás de uma porta do projeto
  (`MessagePublisher`), com fake em memória nos testes.

## Consequências

- Positivas: um serviço fora do ar não perde mensagens (filas duráveis); o
  dd-trace propaga o trace pelo AMQP.
- Negativas: mais um componente para operar; consistência eventual (a OS
  muda de status alguns ms depois do evento).
