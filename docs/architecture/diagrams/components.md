# Diagrama de componentes — Fase 3 (visão cloud-wide)

Visão consolidada dos 4 repositórios, nuvens envolvidas, APIs, banco e
monitoramento. Decisões referenciadas: todas as RFCs/ADRs em
[`../rfcs/`](../rfcs/) e [`../adrs/`](../adrs/).

```mermaid
flowchart TB
    Cliente(["Cliente final<br/>(app/portal)"])
    Staff(["Recepcionista/Mecânico"])

    subgraph AWS["AWS — 15SOAT-Fase1-lambda (Epic 5)"]
        APIGW["API Gateway<br/>(HTTP API)"]
        Lambda["Lambda: 15SOAT-Fase1-cpf-auth<br/>Node.js 20"]
        APIGW --> Lambda
    end

    subgraph Dokploy["Dokploy — nuvem primária (ADR-001)"]
        direction TB
        Traefik["Traefik<br/>(Gateway da app — controle e roteamento)"]

        subgraph AppInfra["15SOAT-Fase1-kubernetes (Epic 3)"]
            App["dokploy_application<br/>15SOAT-Fase1-app<br/>2 réplicas fixas (ADR-006)"]
        end

        subgraph DbInfra["15SOAT-Fase1-database (Epic 2)"]
            DB[("dokploy_postgres<br/>15SOAT-Fase1-postgres")]
        end

        subgraph ObsInfra["Observabilidade (Epic 6)"]
            Agent["datadog-agent<br/>(dokploy_compose)"]
        end

        Traefik --> App
        App --> DB
        Agent -.->|docker.sock: CPU/mem de todo container| App
        Agent -.-> DB
        App -.->|logs JSON estruturados<br/>stdout/stderr| Agent
    end

    subgraph DD["Datadog (SaaS)"]
        Monitors["datadog_monitor × 3<br/>CPU · memória · falhas de OS"]
        Logs["Log Management"]
    end

    subgraph CICD["CI/CD — 4 repos, GitHub Actions (ADR-002)"]
        CI4["15SOAT-Fase1<br/>ci-cd.yml"]
        CI1["15SOAT-Fase1-lambda<br/>ci.yml + deploy.yml"]
        CI2["15SOAT-Fase1-kubernetes<br/>ci.yml + deploy.yml"]
        CI3["15SOAT-Fase1-database<br/>ci.yml + deploy.yml"]
    end

    Cliente -->|"POST /auth/cpf"| APIGW
    Lambda -->|"POST /internal/clients/cpf-lookup<br/>X-Internal-Api-Key"| Traefik
    Cliente -->|"Bearer JWT (RS256)<br/>/api/v1/*"| Traefik
    Staff -->|"Sanctum token<br/>/api/v1/*"| Traefik

    Agent --> Logs
    Agent --> Monitors

    CI4 -->|"build+push imagem (GHCR)<br/>workflow_dispatch"| CI2
    CI1 -.->|deploy| Lambda
    CI2 -.->|deploy| AppInfra
    CI2 -.->|deploy| ObsInfra
    CI3 -.->|deploy| DbInfra
```

## Legenda de status (nesta sessão de desenvolvimento)

| Componente | Código/Terraform | `terraform validate` / testes | `apply` real |
|---|---|---|---|
| App principal | ✅ | ✅ 150 testes PHPUnit | ⏳ requer Dokploy real |
| Banco (Epic 2) | ✅ | ✅ contra `vanillauys/dokploy` | ⏳ |
| App/domínio (Epic 3) | ✅ | ✅ | ⏳ |
| Lambda (Epic 5) | ✅ | ✅ 19 testes `node:test` | ⏳ requer conta AWS |
| Datadog (Epic 6) | ✅ (escopo documentado em ADR-009) | ✅ contra `DataDog/datadog` | ⏳ requer conta Datadog conectada |

Nenhum `terraform apply`/deploy real foi executado nesta sessão — todas as
credenciais de nuvem (Dokploy, AWS, Datadog) pertencem ao usuário e não
foram compartilhadas na conversa, corretamente. "Links para os deploys
ativos" (pedido em `evolucao_fase3`) ficam pendentes até a aplicação real
acontecer — não fabricados aqui.
