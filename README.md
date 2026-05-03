# 15SOAT - Fase 1 - Tech Challenge

API RESTful para gestão de ordens de serviço (OS) de uma oficina mecânica.

---

## Stack

| Camada       | Tecnologia                        |
|--------------|-----------------------------------|
| Linguagem    | PHP 8.3                           |
| Framework    | Laravel 13                        |
| Banco        | PostgreSQL 16 (produção/Docker)   |
| Banco testes | SQLite `:memory:`                 |
| Auth         | Laravel Sanctum (API tokens)      |
| Docs         | L5-Swagger / OpenAPI 3            |
| Fila         | Database driver                   |
| Container    | Docker + Docker Compose           |

---

## Como rodar

> Necessário ter o Docker instalado na máquina.

```bash
docker compose up --build -d
```

O container sobe o banco, roda as migrations, popula os dados de demonstração e inicia o servidor.

| Recurso    | URL                                       |
|------------|-------------------------------------------|
| API        | http://localhost:8000/api/v1/             |
| Swagger UI | http://localhost:8000/api/documentation   |

---

## Testar a API

Importe o arquivo `Tech_Challenge.postman_collection.json` no Postman.

---

## Credenciais de demo

| Perfil        | E-mail                | Senha    | Pode fazer                                                               |
|---------------|-----------------------|----------|--------------------------------------------------------------------------|
| Recepcionista | recepcao@workshop.com | password | CRUD clientes/veículos, criar OS, entregar veículo após pagamento        |
| Mecânico      | mecanico@workshop.com | password | CRUD serviços/itens (estoque), criar OS, diagnóstico, execução, orçamento|
| Cliente       | carlos@example.com    | password | Consultar suas OSs, aprovar/cancelar orçamento, pagar OS                 |

---

## Fluxo de status da OS

```
received → in_diagnosis → awaiting_approval → approved → in_execution → finalized → delivered
                                           └──────────→ cancelled
```

Notificações automáticas são disparadas na criação e nas transições de orçamento gerado, finalização e atraso na retirada (job horário).

---

## Endpoints principais

Todos os endpoints (exceto login) requerem o header:

```
Authorization: Bearer {token}
```

| Método | Rota                                          | Perfil                  | Descrição                                       |
|--------|-----------------------------------------------|-------------------------|-------------------------------------------------|
| POST   | `/api/v1/auth/login`                          | público                 | Login — retorna token Sanctum                   |
| POST   | `/api/v1/auth/logout`                         | autenticado             | Logout                                          |
| GET    | `/api/v1/auth/me`                             | autenticado             | Dados do usuário autenticado                    |
| GET    | `/api/v1/clients`                             | recepcionista/mecânico  | Listar clientes                                 |
| POST   | `/api/v1/clients`                             | recepcionista/mecânico  | Cadastrar cliente                               |
| GET    | `/api/v1/vehicles`                            | recepcionista/mecânico  | Listar veículos                                 |
| GET    | `/api/v1/services`                            | recepcionista/mecânico  | Listar serviços do catálogo (com itens)         |
| POST   | `/api/v1/services`                            | recepcionista/mecânico  | Cadastrar serviço (com lista de itens)          |
| GET    | `/api/v1/items`                               | recepcionista/mecânico  | Listar itens em estoque                         |
| POST   | `/api/v1/items`                               | recepcionista/mecânico  | Cadastrar item (`type`: `insumo` ou `peca`)     |
| PUT    | `/api/v1/items/{id}`                          | recepcionista/mecânico  | Atualizar item / ajustar estoque                |
| GET    | `/api/v1/service-orders`                      | todos                   | Listar ordens de serviço                        |
| POST   | `/api/v1/service-orders`                      | recepcionista/mecânico  | Criar OS                                        |
| POST   | `/api/v1/service-orders/{id}/services`        | mecânico                | Adicionar serviço (cria itens automaticamente)  |
| POST   | `/api/v1/service-orders/{id}/items`           | mecânico                | Adicionar item manualmente à OS                 |
| DELETE | `/api/v1/service-orders/{id}/items/{itemId}`  | mecânico                | Remover item da OS                              |
| POST   | `/api/v1/service-orders/{id}/generate-budget` | mecânico                | Gerar orçamento                                 |
| POST   | `/api/v1/service-orders/{id}/approve`         | cliente                 | Aprovar orçamento                               |
| POST   | `/api/v1/service-orders/{id}/cancel`          | cliente                 | Cancelar orçamento                              |
| POST   | `/api/v1/service-orders/{id}/pay`             | cliente                 | Pagar OS                                        |
| POST   | `/api/v1/service-orders/{id}/deliver`         | recepcionista           | Entregar veículo                                |
| POST   | `/webhook/messaging`                          | público                 | Webhook externo                                 |

Documentação completa e interativa em **http://localhost:8000/api/documentation**.

---

## Gestão de Itens na OS

Ao adicionar um serviço à OS, os itens necessários para executá-lo são criados automaticamente na lista de materiais da OS. O mecânico pode adicionar ou remover itens manualmente.

A resposta da OS inclui, para cada item:

| Campo               | Descrição                                  |
|---------------------|--------------------------------------------|
| `requested_quantity`| Quantidade necessária para a OS            |
| `total_quantity`    | Quantidade disponível em estoque           |

Isso permite ao frontend alertar o mecânico caso o estoque seja insuficiente antes de iniciar a execução.

---

## Arquitetura

```
app/
├── Contracts/          # Interfaces: PaymentServiceInterface, MessagingServiceInterface
├── Enums/              # UserRole, ServiceOrderStatus, ItemType (insumo/peca)
├── Http/
│   ├── Controllers/Api/V1/   # AuthController, ClientController, VehicleController,
│   │                         # ServiceController, ItemController, ServiceOrderController
│   ├── Middleware/     # EnsureRole — verifica UserRole no token Sanctum
│   ├── Requests/       # StoreXxx / UpdateXxx
│   └── Resources/      # Transformadores de resposta JSON
├── Jobs/               # SendFineNotificationJob (dispara alerta de atraso)
├── Models/             # User, Vehicle, Service, Item, ServiceItem,
│   │                   # ServiceOrder, ServiceOrderService, ServiceOrderItem
├── Providers/          # AppServiceProvider (bind stubs às interfaces)
└── Services/           # StubPaymentService, StubMessagingService
```

Integrações externas (pagamento e mensageria) são implementadas como **stubs** — contratos definidos via interface, prontos para substituição por implementações reais.

---

## Testes

Rodar todos os testes:

```bash
composer run test
```

Rodar com relatório de cobertura no terminal + arquivos para o SonarQube:

```bash
XDEBUG_MODE=coverage php artisan test --coverage --coverage-clover=coverage.xml --log-junit=test-results.xml
```

**Resultado atual: 89 testes, 208 assertions.**

---

## SonarQube

Sobe o SonarQube (consome ~2 GB de RAM):

```bash
docker compose --profile sonar up -d
```

Acesse em **http://localhost:9000** (login: `admin`).

Gere o `coverage.xml` e rode o scanner:

```bash
XDEBUG_MODE=coverage php artisan test --coverage-clover=coverage.xml --log-junit=test-results.xml

export SONAR_TOKEN=<seu_token>
docker compose --profile sonar run sonarscanner
```
