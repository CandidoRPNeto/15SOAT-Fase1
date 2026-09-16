# ADR-005: Provider Terraform `vanillauys/dokploy`

**Status**: Aceita
**Data**: 2026-09-02

## Contexto

O ecossistema de providers Terraform para Dokploy é fragmentado: pelo menos
6 forks comunitários existem (`vanillauys`, `ahmedali6`, `Ca-moes`, `j0bIT`,
`TheFrozenFire`, `Feng-Brasil`), nenhum oficial da HashiCorp nem do time do
Dokploy, todos com poucos downloads no Registry. [RFC-002](../rfcs/rfc-002-managed-database-strategy.md)
já escolheu `vanillauys/dokploy` para o banco (Epic 2); esta ADR estende a
mesma escolha para o recurso de aplicação (Epic 3), evitando dois providers
distintos gerindo o mesmo servidor Dokploy.

## Decisão

`vanillauys/dokploy`, versão pinada em `0.10.2` (exata, não `~>`), em todos
os repositórios de infra da Fase 3. Critério de escolha: é o fork com
schema mais completo (`dokploy_application`, `dokploy_domain`,
`dokploy_postgres`/`mysql`/`mariadb`/`mongo`/`redis`/`libsql`,
`dokploy_backup`, `dokploy_network`, guias próprios de "deploy semantics",
"secrets" e "adopting an existing instance"), publicado mais recentemente
(2026-09-01) e testado contra uma versão do Dokploy server documentada
(v0.30.3).

## Consequências

- Positivas: um único provider, um único modelo mental (`project >
  environment > service`) para todos os repos de infra; documentação rica
  o bastante para não depender de engenharia reversa do código-fonte do
  provider.
- Negativas / trade-offs: é pré-1.0 — "breaking changes chegam em minor
  releases até v1.0.0" (aviso do próprio provider). Pin exato (não `~>`)
  é obrigatório; atualizar a versão é uma mudança deliberada, não um
  `terraform init` de rotina. É um fork mantido por uma pessoa/comunidade
  pequena — sem garantia de suporte de longo prazo; se o projeto for
  abandonado, a migração para outro fork (schemas parecidos, não idênticos)
  é um retrabalho a considerar no futuro, não algo a resolver agora.
- Risco operacional documentado no provider: a API do Dokploy responde
  `401` (não `429`) quando o limite de requisições da API key é excedido —
  falha de autenticação e esgotamento de rate limit são indistinguíveis
  sem essa nota. Registrado nos READMEs dos repos de infra (Epic 2/3) para
  não ser confundido com credencial errada num apply real.
