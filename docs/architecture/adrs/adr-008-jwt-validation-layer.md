# ADR-008: Validação do JWT acontece na aplicação, não no gateway

**Status**: Aceita
**Data**: 2026-09-02

## Contexto

Um JWT emitido pelo Lambda ([RFC-003](../rfcs/rfc-003-cpf-auth-strategy.md))
protege rotas de `/api/v1`. Duas camadas poderiam validar a assinatura
antes de deixar a requisição passar: o Traefik do Dokploy (via middleware
`forwardAuth`, chamando um serviço validador) ou a própria aplicação
Laravel.

## Decisão

Validação acontece **na aplicação Laravel**, via guard `client_jwt`
(`Auth::viaRequest`, `AppServiceProvider::resolveUserFromClientJwt`) — não
no Traefik.

## Consequências

- Positivas: sem serviço validador novo pra implantar/operar (um
  `forwardAuth` do Traefik precisaria de um endpoint HTTP dedicado só pra
  validar o token, adicionando um hop de rede a cada requisição
  autenticada); reaproveita a checagem de `role`/`status` que já precisa
  tocar o banco de qualquer forma — fazer isso duas vezes (gateway +
  aplicação) seria redundante; mesmo padrão arquitetural que o Sanctum já
  usa neste projeto (validação de auth dentro da aplicação).
- Negativas / trade-offs: requisições com token inválido ainda chegam até
  o processo PHP antes de serem rejeitadas (mais carga do que rejeitar no
  gateway) — aceitável na escala deste projeto; se isso virar um problema
  real (ex.: alvo de abuso/DoS), esta decisão pode ser revisitada com um
  `forwardAuth` no Traefik sem quebrar o guard existente (ele continuaria
  validando, só passaria a receber menos tráfego inválido).
