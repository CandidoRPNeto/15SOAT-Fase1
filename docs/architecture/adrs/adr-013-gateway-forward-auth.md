# ADR-013: Autenticação centralizada no gateway (Traefik ForwardAuth)

**Status**: Aceita
**Data**: 2026-11-16

## Contexto

Os tokens Sanctum ficam no banco do OS Service, e o Billing/Execution não
podem ler esse banco. O JWT RS256 do Lambda poderia ser validado em cada
serviço, mas o token da equipe (Sanctum) não.

## Decisão

O Traefik aplica o middleware **ForwardAuth** em todas as rotas
`/api/v1/*`, exceto login. Ele chama `GET /api/v1/auth/verify` no OS
Service, que aceita Sanctum ou o JWT do Lambda e responde 200 com os headers
`X-User-Id`, `X-User-Role` e `X-User-Client-Id`, ou 401.

O Traefik copia esses headers para a requisição de destino
(`authResponseHeaders`), sobrescrevendo qualquer valor que o cliente tenha
enviado. Billing e Execution usam o middleware `TrustGatewayIdentity`, só
leem os headers e não guardam credencial.

## Consequências

- Positivas: login e regras de token num lugar só; os serviços novos não
  dependem de Sanctum.
- Negativas: uma chamada extra por requisição. Ela é barata (lookup do token
  indexado).
- Ressalva: os Services de Billing/Execution são ClusterIP e só recebem
  tráfego pelo Traefik. O kindnet não aplica NetworkPolicy, então isso é uma
  convenção do ambiente local, documentada aqui.
