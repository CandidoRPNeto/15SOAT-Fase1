# ADR-007: AWS API Gateway — escopo: só a Function Serverless

**Status**: Aceita
**Data**: 2026-09-02

## Contexto

`evolucao_fase3` pede "um API Gateway compatível com Laravel... para
controle e roteamento" e, separadamente, uma Function Serverless de auth.
[ADR-001](adr-001-dokploy-as-cloud.md) já estabeleceu o Traefik embutido no
Dokploy como o gateway da aplicação principal. Falta esclarecer: o Lambda
(AWS, [RFC-001](../rfcs/rfc-001-cloud-strategy.md)) precisa do seu próprio
gateway, ou é invocado de outra forma?

## Decisão

**AWS API Gateway fica na frente só do Lambda** — não da aplicação
principal (essa continua atrás do Traefik/Dokploy). O requisito de "API
Gateway" da Fase 3 é satisfeito por **dois componentes complementares**,
não um só:

- **Traefik (Dokploy)**: controle e roteamento da aplicação principal —
  já existente, ADR-001.
- **AWS API Gateway**: único ponto de entrada HTTPS pro Lambda de auth
  (`POST /auth/cpf`, por exemplo) — é o jeito padrão de expor um Lambda
  como endpoint HTTP na AWS (o Lambda sozinho não fala HTTP diretamente).

## Consequências

- Positivas: cada gateway faz o que sabe fazer melhor, sem forçar tráfego
  do Lambda a atravessar o Dokploy (que não hospeda o Lambda) nem o
  inverso; é a integração AWS mais direta e documentada pra expor Lambda
  via HTTP.
- Negativas / trade-offs: dois gateways distintos pra documentar e
  operar (dashboards, logs, configuração) em vez de um só — custo aceito
  conscientemente como parte de manter o Lambda numa segunda nuvem
  ([RFC-001](../rfcs/rfc-001-cloud-strategy.md)).
- Tipo de API Gateway: HTTP API (não REST API) — mais barato e mais
  simples pra um único endpoint proxy-Lambda, sem necessidade dos recursos
  avançados (API keys de uso, request/response transformation complexa)
  só disponíveis na REST API.
