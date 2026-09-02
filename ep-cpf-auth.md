# Epic 5 — Auth Lambda + API Gateway + validação JWT

**Depende de**: Epic 1, Epic 4 (app precisa estar pronta pra rodar em nuvem antes de proteger rotas com um auth novo).
**Repos de código**: `15SOAT-Fase1`, `workshop-os-lambda-auth`.
**Objetivo**: Lambda (CPF → status do cliente → JWT), API Gateway AWS na frente dele, guard `client_jwt` protegendo rotas de `/api/v1` além do Sanctum.

**Nota de sequenciamento**: branch empilhada
(`feature/2026/09/epic5-cpf-auth` a partir de
`feature/2026/09/epic4-cloud-adaptation`), mesma lógica dos epics anteriores.

## Decisões arquiteturais

- [x] `docs/architecture/rfcs/rfc-003-cpf-auth-strategy.md` — acesso do Lambda ao dado (endpoint interno, não DB direto), RS256, aditivo ao Sanctum, coluna `status` nova
- [x] `docs/architecture/adrs/adr-007-aws-api-gateway.md` — escopo: só a frente do Lambda, não da app
- [x] `docs/architecture/adrs/adr-008-jwt-validation-layer.md` — validação na aplicação, não no Traefik
- [x] `docs/architecture/diagrams/sequence-auth.md`
- [x] `docs/architecture/diagrams/sequence-service-order.md`

## App principal (`15SOAT-Fase1`)

- [x] `App\Enums\UserStatus` (active/blocked) + migration `add_status_to_users_table` + `UserFactory` (`status` explícito no `create()`, state `blocked()`)
- [x] `EnsureInternalApiKey` (segredo compartilhado, `hash_equals`)
- [x] `InternalController::cpfLookup` — `POST /internal/clients/cpf-lookup`, fora de `/api/v1`, com `@OA` — busca por CPF ignorando pontuação (`REPLACE` aninhado, compatível SQLite+Postgres)
- [x] `routes/internal.php` + registro em `bootstrap/app.php`
- [x] Guard `client_jwt` (`Auth::viaRequest`, RS256 via `firebase/php-jwt`) em `AppServiceProvider` — valida assinatura, `iss`, `exp`, role=client, status=active
- [x] `routes/api.php`: `auth:sanctum,client_jwt` (aditivo)
- [x] `.env.example` / `config/services.php` / `config/auth.php` atualizados
- [x] Testes: `InternalCpfLookupTest` (6 casos) + `ClientJwtAuthTest` (7 casos, **par RS256 real gerado por teste**, não mockado) — 13 novos
- [x] `php artisan l5-swagger:generate` rodado — endpoint aparece no spec
- [x] Suíte completa: 149 passou (era 136 antes deste epic)

## Lambda (`workshop-os-lambda-auth`)

- [ ] Runtime Node.js/TypeScript — validação de CPF (checksum), chamada ao endpoint interno, emissão de JWT RS256
- [ ] Terraform: `aws_lambda_function` + `aws_apigatewayv2_api` (HTTP API) + integração + rota + permissão
- [ ] README preenchido

## Fechamento do epic

- [ ] Push das branches (sem PR ainda)
- [ ] Apagar este arquivo e a linha em `spec.md` só depois dos PRs mergeados
