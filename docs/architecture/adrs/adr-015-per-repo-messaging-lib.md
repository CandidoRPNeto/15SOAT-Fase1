# ADR-015: Código de mensageria copiado por repo, não pacote compartilhado

**Status**: Aceita
**Data**: 2026-11-16

## Contexto

Os 3 serviços precisam do mesmo código-base de mensageria: envelope, porta
`MessagePublisher`, adapter RabbitMQ, outbox e inbox, além dos middlewares de
correlação e latência. A regra geral do projeto é não duplicar código.

## Decisão

Cada repo mantém sua cópia (~300 linhas). Não é criado um pacote Composer
compartilhado.

## Consequências

- Positivas: autonomia de deploy (mudar a lib num serviço não força release
  nos outros); nenhum 4º repo nem registry de pacotes para manter.
- Negativas: uma correção na lib precisa ser replicada à mão. O contrato que
  importa entre os serviços é o **envelope da mensagem** (documentado no
  AsyncAPI do produtor), não o código.
- Revisitar se surgir um 4º serviço consumidor.
