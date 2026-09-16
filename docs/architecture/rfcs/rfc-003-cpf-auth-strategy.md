# RFC-003: Estratégia de autenticação por CPF

**Status**: Aceita
**Data**: 2026-09-02
**Autor(es)**: Candido, com apoio de IA

## Contexto

`evolucao_fase3` exige: uma Function Serverless que (1) valida o CPF do
cliente, (2) consulta existência e status na base, (3) emite um JWT válido
pras APIs protegidas — e que rotas sensíveis exijam essa autenticação.
[RFC-002](rfc-002-managed-database-strategy.md) já decidiu que a function é
AWS Lambda real. Faltam decidir: como o Lambda acessa os dados do cliente,
o esquema de assinatura do JWT, se isso substitui ou complementa o login
Sanctum existente, e como a coluna `status` (que não existia) é modelada.

## Opções consideradas

### 1. Acesso do Lambda aos dados do cliente

- **(a) Lambda chama um endpoint interno da aplicação Laravel.** Banco
  nunca exposto à internet; reaproveita `User`/Eloquent/validação já
  existentes. Custo: acopla a disponibilidade do login à disponibilidade
  da app principal, e adiciona um hop de rede (Lambda → HTTPS → Laravel →
  Postgres).
- **(b) Lambda conecta direto no Postgres do Dokploy** (usuário read-only).
  Menor latência, sem dependência da app estar de pé. Custo: exige abrir o
  Postgres do Dokploy pra internet (IPs de saída do Lambda não são fixos
  nem previsíveis sem VPC peering/NAT — infraestrutura adicional
  significativa pra um projeto solo) — expande a superfície de ataque do
  banco justamente na função que a Fase 3 elege "segurança" como objetivo
  explícito.

**Decisão: (a).** Segurança > latência aqui — nenhum requisito de
performance justifica abrir o banco à internet. Endpoint:
`POST /internal/clients/cpf-lookup`, protegido por `EnsureInternalApiKey`
(segredo compartilhado só entre Lambda e Laravel, header
`X-Internal-Api-Key`, `hash_equals` contra timing attack), fora do prefixo
`/api/v1` (não é rota de negócio).

### 2. Esquema de assinatura do JWT

- **(a) HS256** (segredo simétrico). Mais simples de implementar, mas o
  mesmo segredo precisa existir nos dois lados — sincronizar entre AWS
  Secrets Manager e as env vars do Dokploy é uma fonte extra de erro
  operacional (rotação, digitação, etc.).
- **(b) RS256** (par de chaves assimétrico). Lambda assina com a chave
  privada (só existe na AWS); Laravel só precisa da chave pública, que não
  é segredo — pode até vazar sem comprometer nada, já que não assina nada.

**Decisão: (b), RS256.** Elimina a necessidade de sincronizar segredo entre
as duas nuvens.

### 3. Substitui ou complementa o login Sanctum existente?

- **(a) Substituir**: clientes deixam de logar por email+senha.
- **(b) Complementar**: JWT por CPF é um método de auth adicional; Sanctum
  continua funcionando pra todos os perfis, inclusive cliente.

**Decisão: (b), aditivo.** `Route::middleware('auth:sanctum,client_jwt')` —
Laravel tenta cada guard em ordem. Evita quebrar a suíte de testes e o
Postman collection existentes (que usam email+senha pra `client` também),
e o enunciado não pede explicitamente a remoção do método atual.

### 4. Coluna `status` do cliente

Não existia (`users` só tinha `role`). Adicionada via migration
(`2026_09_02_000001_add_status_to_users_table.php`): enum
`active`/`blocked` (`App\Enums\UserStatus`), default `active`. O guard
`client_jwt` nega acesso pra cliente `blocked` mesmo com token
tecnicamente válido (defesa em profundidade — um token emitido antes do
bloqueio não deveria continuar funcionando até expirar).

## Recomendação

Fluxo completo: cliente informa CPF ao Lambda (via AWS API Gateway, ver
[ADR-007](../adrs/adr-007-aws-api-gateway.md)) → Lambda valida o dígito
verificador do CPF (checksum, sem chamada a serviço externo — consistente
com a convenção de stubs do projeto, `CLAUDE.md` regra 7) → Lambda chama
`POST /internal/clients/cpf-lookup` na app principal → se `exists=true` e
`status=active`, Lambda assina um JWT RS256 (`sub`=user_id, `iss`=
`workshop-os-lambda-auth`, `exp` curto) → devolve o token → cliente usa
esse token como Bearer nas rotas de `/api/v1` (guard `client_jwt`, ver
[ADR-008](../adrs/adr-008-jwt-validation-layer.md)).

## Consequências / próximos passos

- App principal: `EnsureInternalApiKey`, `InternalController::cpfLookup`,
  guard `client_jwt` (`AppServiceProvider::resolveUserFromClientJwt`),
  migration/enum de `status` — implementados neste epic, com testes
  (unitário onde a lógica não depende do container HTTP completo — ver
  `EnsureRole`/middlewares deste projeto sempre testados via feature test;
  feature test fim-a-fim gerando um par de chaves RS256 real por teste,
  não mockado).
- `workshop-os-lambda-auth`: implementação real do Lambda (Node.js — ver
  ADR própria se necessário) + Terraform pro Lambda/API Gateway.
- Diagrama de sequência do fluxo completo em
  `docs/architecture/diagrams/sequence-auth.md`.

## Perguntas em aberto

- Rotação de chave RS256: não modelada nesta fase (troca manual da chave
  pública via env var + redeploy do Lambda com a nova privada, sem
  período de sobreposição) — aceitável pro escopo atual, revisitar se
  virar problema real.
