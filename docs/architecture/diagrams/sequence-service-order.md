# Diagrama de sequência — abertura de Ordem de Serviço (Fase 3)

Mesmo fluxo de negócio da Fase 1/2
(`App\Application\ServiceOrder\OpenServiceOrder`), agora atravessando o
Gateway da Fase 3. Autenticação de quem abre a OS (recepcionista/mecânico)
continua Sanctum — não é uma rota do fluxo de CPF do cliente (ver
[sequence-auth.md](sequence-auth.md)).

```mermaid
sequenceDiagram
    actor Staff as Recepcionista/Mecânico
    participant Traefik as Traefik (Dokploy)
    participant Ctrl as ServiceOrderController
    participant UC as OpenServiceOrder (use case)
    participant Repo as ServiceOrderRepository
    participant DB as Postgres (Dokploy)
    participant Msg as MessagingServiceInterface (stub)
    participant DD as Datadog Agent

    Staff->>Traefik: POST /api/v1/service-orders<br/>Authorization: Bearer <sanctum token>
    Traefik->>Ctrl: (roteia)
    Ctrl->>Ctrl: auth:sanctum + role:receptionist,mechanic
    Ctrl->>UC: execute(client_id, vehicle_id, services[], items[])
    UC->>Repo: create(status=received)
    Repo->>DB: INSERT service_orders
    DB-->>Repo: OS criada (id)

    loop cada serviço informado
        UC->>DB: SELECT service + service_items (peças/insumos exigidos)
        UC->>DB: INSERT service_order_services
        UC->>DB: INSERT service_order_items (automático, do serviço)
    end
    loop cada item manual informado
        UC->>DB: INSERT service_order_items
    end

    UC->>DB: carrega client + vehicle (relations)
    UC->>Msg: notifyOrderCreated(order)
    Note right of Msg: stub — não integra sistema<br/>real de mensageria (CLAUDE.md regra 7)
    UC-->>Ctrl: ServiceOrder (id único)
    Ctrl-->>Traefik: 201 { id, status: received, ... }
    Traefik-->>Staff: 201 { id, status: received, ... }

    par observabilidade (Epic 6)
        Ctrl-)DD: log estruturado JSON<br/>(request_id, latência, status)
    end
```

Notas:
- `client_id` referencia um `User` com `role=client` — o mesmo registro que
  o fluxo de CPF ([sequence-auth.md](sequence-auth.md)) autentica; abrir a
  OS não exige que o cliente esteja logado, só que o recepcionista/mecânico
  informe o `client_id` correto.
- `notifyOrderCreated` é um stub (`StubMessagingService`) — nunca chama um
  serviço de mensageria real, convenção já estabelecida na Fase 1/2.
- O log estruturado (linha pontilhada pro Datadog Agent) é o Epic 6 —
  incluído aqui porque `evolucao_fase3` pede correlação entre requisições
  nos logs, e este é exatamente o tipo de operação que deve aparecer
  correlacionada.
