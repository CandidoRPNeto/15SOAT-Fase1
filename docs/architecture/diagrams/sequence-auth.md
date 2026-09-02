# Diagrama de sequência — autenticação por CPF (Fase 3)

Fluxo completo: emissão do token (Lambda) + uso do token numa rota protegida
da aplicação principal. Decisões referenciadas:
[RFC-003](../rfcs/rfc-003-cpf-auth-strategy.md),
[ADR-007](../adrs/adr-007-aws-api-gateway.md),
[ADR-008](../adrs/adr-008-jwt-validation-layer.md).

```mermaid
sequenceDiagram
    actor Cliente
    participant APIGW as AWS API Gateway
    participant Lambda as Lambda (15SOAT-Fase1-lambda)
    participant Traefik as Traefik (Dokploy)
    participant App as Laravel (15SOAT-Fase1)
    participant DB as Postgres (Dokploy)

    rect rgb(235, 245, 255)
    note over Cliente,DB: 1. Emissão do token
    Cliente->>APIGW: POST /auth/cpf { cpf }
    APIGW->>Lambda: invoke(event)
    Lambda->>Lambda: valida dígito verificador do CPF
    alt CPF com formato inválido
        Lambda-->>APIGW: 400 Bad Request
        APIGW-->>Cliente: 400 Bad Request
    else CPF bem formado
        Lambda->>Traefik: POST /internal/clients/cpf-lookup<br/>X-Internal-Api-Key
        Traefik->>App: (roteia)
        App->>DB: SELECT ... WHERE role=client AND cpf_cnpj~=?
        DB-->>App: cliente (id, status) ou nada
        App-->>Traefik: 200 {exists, user_id, status} / 404
        Traefik-->>Lambda: (idem)
        alt não existe ou status=blocked
            Lambda-->>APIGW: 403 Forbidden
            APIGW-->>Cliente: 403 Forbidden
        else existe e status=active
            Lambda->>Lambda: assina JWT RS256<br/>(sub=user_id, iss=15SOAT-Fase1-lambda, exp curto)
            Lambda-->>APIGW: 200 { token }
            APIGW-->>Cliente: 200 { token }
        end
    end
    end

    rect rgb(235, 255, 240)
    note over Cliente,DB: 2. Uso do token numa rota protegida
    Cliente->>Traefik: GET /api/v1/service-orders<br/>Authorization: Bearer <jwt>
    Traefik->>App: (roteia)
    App->>App: guard client_jwt:<br/>verifica assinatura (chave pública RS256),<br/>iss, exp
    App->>DB: SELECT user WHERE id = sub
    DB-->>App: cliente
    App->>App: hasRole(client) && isActive()?
    alt token inválido/expirado/cliente bloqueado
        App-->>Traefik: 401 Unauthenticated
        Traefik-->>Cliente: 401 Unauthenticated
    else válido
        App-->>Traefik: 200 { ordens de serviço do cliente }
        Traefik-->>Cliente: 200 { ordens de serviço do cliente }
    end
    end
```

Notas:
- O Lambda nunca acessa o Postgres diretamente — só via
  `POST /internal/clients/cpf-lookup`, decisão de segurança do RFC-003.
- A validação do JWT acontece na aplicação (guard `client_jwt`), não no
  Traefik — ADR-008.
- `X-Internal-Api-Key` é um segredo estático compartilhado só entre Lambda
  e aplicação, distinto do JWT que o cliente final usa.
