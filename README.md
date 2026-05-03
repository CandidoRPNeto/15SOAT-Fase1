# 15SOAT - Fase 1 - Tech Challenge 

API RESTfull para gestão de ordens de serviço(OS) de uma oficina mecânica.

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

> necessario ter o docker instalado na maquina.

```bash
docker compose up --build -d
```

Isso é tudo. O container sobe o banco, roda as migrations, popula os dados de demonstração e inicia o servidor.

| Recurso    | URL                                       |
|------------|-------------------------------------------|
| API        | http://localhost:8000/api/v1/             |
| Swagger UI | http://localhost:8000/api/documentation   |

---

## Testar a API

Importe o arquivo Tech_Challenge.postman_collection.json em seu postman

---


## Credenciais de demo

| Perfil        | E-mail                  | Senha      | Pode fazer                                                                 |
|---------------|-------------------------|------------|----------------------------------------------------------------------------|
| Recepcionista | recepcao@workshop.com   | password   | CRUD clientes/veículos, criar OS, entregar veículo após pagamento          |
| Mecânico      | mecanico@workshop.com   | password   | CRUD serviços/peças, criar OS, diagnóstico, execução, orçamento            |
| Cliente       | carlos@example.com      | password   | Consultar suas OSs, aprovar/cancelar orçamento, pagar OS                   |

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

| Método | Rota                                         | Descrição                        |
|--------|----------------------------------------------|----------------------------------|
| POST   | `/api/v1/auth/login`                         | Login — retorna token Sanctum    |
| POST   | `/api/v1/auth/logout`                        | Logout                           |
| GET    | `/api/v1/auth/me`                            | Dados do usuário autenticado     |
| GET    | `/api/v1/clients`                            | Listar clientes                  |
| POST   | `/api/v1/clients`                            | Cadastrar cliente                |
| GET    | `/api/v1/vehicles`                           | Listar veículos                  |
| GET    | `/api/v1/services`                           | Listar serviços do catálogo      |
| GET    | `/api/v1/parts`                              | Listar peças em estoque          |
| GET    | `/api/v1/service-orders`                     | Listar ordens de serviço         |
| POST   | `/api/v1/service-orders`                     | Criar OS                         |
| POST   | `/api/v1/service-orders/{id}/generate-budget`| Gerar orçamento                  |
| POST   | `/api/v1/service-orders/{id}/approve`        | Cliente aprova orçamento         |
| POST   | `/api/v1/service-orders/{id}/pay`            | Cliente paga OS                  |
| POST   | `/api/v1/service-orders/{id}/deliver`        | Recepcionista entrega veículo    |
| POST   | `/webhook/messaging`                         | Webhook externo (público)        |

Documentação completa e interativa em **http://localhost:8000/api/documentation**.

---

## Arquitetura

```
app/
├── Contracts/          # Interfaces: PaymentServiceInterface, MessagingServiceInterface
├── Enums/              # UserRole, ServiceOrderStatus (com canTransitionTo)
├── Http/
│   ├── Controllers/Api/V1/   # AuthController, ClientController, VehicleController,
│   │                         # ServiceController, PartController, ServiceOrderController
│   ├── Middleware/     # EnsureRole — verifica UserRole no token Sanctum
│   ├── Requests/       # StoreXxx / UpdateXxx
│   └── Resources/      # Transformadores de resposta JSON
├── Jobs/               # SendFineNotificationJob (dispara alerta de atraso)
├── Models/             # User, Vehicle, Service, Part, ServiceOrder, ...
├── Providers/          # AppServiceProvider (bind stubs às interfaces)
└── Services/           # StubPaymentService, StubMessagingService
```

Integrações externas (pagamento e mensageria) são implementadas como **stubs** — contratos definidos via interface, prontos para substituição por implementações reais.

---

## Testes

76 testes / 167 assertions — PHPUnit com SQLite `:memory:`.

```bash
composer run test
```
