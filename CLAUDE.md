# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Workshop OS API** — Laravel 13 (PHP ^8.3) REST API para gestão de ordens de serviço de oficina mecânica.

- **Database**: PostgreSQL (produção/Docker), SQLite `:memory:` (testes)
- **Auth**: Laravel Sanctum (API tokens)
- **Docs**: L5-Swagger / OpenAPI 3 em `/api/documentation`
- **Queue**: Database driver; scheduler rodando `SendFineNotificationJob` a cada hora
- **Testes**: PHPUnit — 76 testes, 167 assertions; cobertura em `coverage.xml`
- **CI**: `sonar-project.properties` configurado; SonarQube disponível via `--profile sonar`

## Regras do projeto

1. **Toda função adicionada deve ter um teste unitário criado junto.**
2. **Toda rota adicionada deve ter anotação Swagger (@OA) e teste de feature.**
3. Rotas da API seguem o prefixo `/api/v1/`.
4. Webhook externo está em `POST /webhook/messaging` (sem prefixo `/api`).
5. Use `UpdateXxxRequest` (com `sometimes`) para PUTs; `StoreXxxRequest` (com `required`) para POSTs.
6. Pesquisas case-insensitive: use `LOWER(col) LIKE ?` (compatível com SQLite nos testes).
7. Stubs externos: `StubPaymentService`, `StubMessagingService` — não implementar integrações reais.

## Perfis de usuário e permissões

| Perfil         | Pode fazer                                                                    |
|----------------|-------------------------------------------------------------------------------|
| `receptionist` | CRUD clientes/veículos, criar OS, entregar veículo após pagamento             |
| `mechanic`     | CRUD serviços/peças (estoque), criar OS, diagnóstico, execução, orçamento     |
| `client`       | Consultar suas OSs, aprovar/cancelar orçamento, pagar OS                      |

Middleware: `role:receptionist`, `role:mechanic`, `role:client`, ou combinado `role:receptionist,mechanic`.

## Fluxo de status da OS

```
received → in_diagnosis → awaiting_approval → approved → in_execution → finalized → delivered
                                           └──────────→ cancelled
```

Mensagens disparadas automaticamente (via `MessagingServiceInterface`):
- Criação da OS → `notifyOrderCreated`
- Orçamento gerado → `notifyBudgetReady`
- OS finalizada → `notifyPickupReady`
- OS finalizada há +24h sem pagamento (job horário) → `notifyPickupOverdue`

## Commands

### Setup inicial (local, sem Docker)
```bash
# Crie o banco PostgreSQL antes:
# createdb workshop_os
composer run setup
php artisan db:seed
```

### Com Docker
```bash
docker compose up -d          # sobe app + postgres
docker compose exec app php artisan migrate --seed
```

### SonarQube (perfil separado — consome ~2GB RAM)
```bash
docker compose --profile sonar up -d    # sobe SonarQube na porta 9000
docker compose --profile sonar run sonarscanner  # roda análise
```

### Desenvolvimento (sem Docker)
```bash
composer run dev   # server + queue + logs + vite em paralelo
```

### Testes
```bash
composer run test                          # todos os testes (SQLite :memory:)
php artisan test --no-coverage             # sem relatório de cobertura
php artisan test --filter=ServiceOrderTest # um teste específico
XDEBUG_MODE=coverage php artisan test --coverage  # com cobertura (requer Xdebug)
```

### Cobertura para SonarQube
```bash
XDEBUG_MODE=coverage php artisan test \
  --coverage-clover=coverage.xml \
  --log-junit=test-results.xml
```

### Code style
```bash
./vendor/bin/pint              # fix all files
./vendor/bin/pint app/         # fix specific directory
```

### Gerar documentação Swagger
```bash
php artisan l5-swagger:generate   # gera em storage/api-docs/api-docs.json
# Acesse: http://localhost:8000/api/documentation
```

### Artisan útil
```bash
php artisan migrate                           # rodar migrations
php artisan migrate:fresh --seed             # reset completo
php artisan schedule:work                    # rodar scheduler localmente
php artisan tinker                           # REPL
```

## Architecture

```
app/
├── Contracts/          # Interfaces: PaymentServiceInterface, MessagingServiceInterface
├── Enums/              # UserRole, ServiceOrderStatus (com canTransitionTo)
├── Http/
│   ├── Controllers/Api/V1/  # AuthController, ClientController, VehicleController,
│   │                        # ServiceController, PartController, ServiceOrderController,
│   │                        # WebhookController (com anotações @OA\*)
│   ├── Middleware/     # EnsureRole — verifica UserRole no token Sanctum
│   ├── Requests/       # StoreXxx / UpdateXxx (todos com authorize(): true)
│   └── Resources/      # UserResource, VehicleResource, ServiceResource,
│                       # PartResource, ServiceOrderResource
├── Jobs/               # SendFineNotificationJob (dispatchable, shouldQueue)
├── Models/             # User, Vehicle, Service, Part, ServiceOrder,
│                       # ServiceOrderService, ServiceOrderPart
├── Providers/          # AppServiceProvider (binds stubs às interfaces)
└── Services/           # StubPaymentService, StubMessagingService

routes/
├── api.php             # /api/v1/* (todos autenticados exceto login)
├── webhook.php         # POST /webhook/messaging (público, sem /api)
├── web.php             # welcome page
└── console.php         # Schedule::job(SendFineNotificationJob)->hourly()

database/
├── factories/          # UserFactory (receptionist/mechanic/client), VehicleFactory,
│                       # ServiceFactory, PartFactory, ServiceOrderFactory
├── migrations/         # users (role/cpf_cnpj/phone), vehicles, services, parts,
│                       # service_orders, service_order_services, service_order_parts
└── seeders/            # DatabaseSeeder — popula perfis e catálogo inicial
```

### Por que PostgreSQL?

Escolha técnica justificada:
1. **ACID completo** — transações financeiras (pagamento de OS) requerem integridade garantida
2. **Enum nativo** — `ServiceOrderStatus` mapeado diretamente como `ENUM` na coluna
3. **Concorrência real** — MVCC sem locks de tabela inteira (crítico para múltiplos mecânicos simultâneos)
4. **JSONB e índices parciais** — extensível para futuras buscas em histórico de OS
5. **Ecosystem Docker** — imagem oficial `postgres:16-alpine` com healthcheck confiável para CI/CD
6. **Padrão de produção** — SQLite é apenas para testes (`:memory:` no phpunit.xml)

## Chaves de configuração relevantes

| Variável                  | Valor padrão       | Descrição                              |
|---------------------------|--------------------|----------------------------------------|
| `DB_CONNECTION`           | `pgsql`            | PostgreSQL em produção                 |
| `DB_DATABASE`             | `workshop_os`      | Nome do banco                          |
| `QUEUE_CONNECTION`        | `database`         | Fila para o job de notificação         |
| `L5_SWAGGER_GENERATE_ALWAYS` | `false`         | Defina `true` em dev para auto-gerar   |
| `L5_SWAGGER_CONST_HOST`   | `http://localhost:8000` | Base URL no Swagger UI             |
| `SONAR_TOKEN`             | (vazio)            | Token para SonarScanner no CI         |
