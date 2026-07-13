# Documento de Requisitos — Workshop OS API

**Versão:** 1.0  
**Data:** 2026-05-03  
**Projeto:** Workshop OS — API REST de gestão de ordens de serviço para oficina mecânica

---

## 1. Requisitos Funcionais

### RF-01 — Autenticação

| ID | Descrição |
|----|-----------|
| RF-01.1 | O sistema deve permitir que qualquer usuário autentique via `POST /api/v1/auth/login` com e-mail e senha, recebendo um token opaco (Bearer). |
| RF-01.2 | O sistema deve permitir que o usuário autenticado encerre sua sessão via `POST /api/v1/auth/logout`, invalidando o token atual. |
| RF-01.3 | O sistema deve retornar os dados do usuário autenticado via `GET /api/v1/auth/me`. |

---

### RF-02 — Controle de Acesso por Perfil

| ID | Descrição |
|----|-----------|
| RF-02.1 | O sistema deve suportar três perfis de usuário: `receptionist`, `mechanic` e `client`. |
| RF-02.2 | O acesso a cada endpoint deve ser restrito ao(s) perfil(is) autorizado(s), conforme tabela de permissões. Requisições de perfis não autorizados devem receber HTTP 403. |
| RF-02.3 | O perfil do usuário deve ser verificado a partir do token Sanctum em cada requisição autenticada. |

**Matriz de permissões por recurso:**

| Recurso | receptionist | mechanic | client |
|---------|:---:|:---:|:---:|
| CRUD Clientes | ✓ | ✓ | — |
| CRUD Veículos | ✓ | ✓ | — |
| CRUD Serviços (catálogo) | ✓ | ✓ | — |
| CRUD Itens (estoque) | ✓ | ✓ | — |
| Criar OS | ✓ | ✓ | — |
| Listar/consultar OS | ✓ (todas) | ✓ (todas) | ✓ (somente as suas) |
| Adicionar serviço/item à OS | — | ✓ | — |
| Gerar orçamento | — | ✓ | — |
| Iniciar execução | — | ✓ | — |
| Finalizar OS | — | ✓ | — |
| Aprovar/cancelar orçamento | — | — | ✓ (somente as suas) |
| Pagar OS | — | — | ✓ (somente as suas) |
| Entregar veículo | ✓ | — | — |
| Ver estatísticas | ✓ | ✓ | — |

---

### RF-03 — Gestão de Clientes

| ID | Descrição |
|----|-----------|
| RF-03.1 | O sistema deve permitir cadastrar, consultar, listar, atualizar e remover clientes (`apiResource /api/v1/clients`). |
| RF-03.2 | A listagem de clientes deve suportar busca por nome de forma case-insensitive. |
| RF-03.3 | O cadastro de cliente deve exigir CPF/CNPJ e telefone. |

---

### RF-04 — Gestão de Veículos

| ID | Descrição |
|----|-----------|
| RF-04.1 | O sistema deve permitir cadastrar, consultar, listar, atualizar e remover veículos (`apiResource /api/v1/vehicles`). |
| RF-04.2 | Um veículo deve estar associado a um cliente cadastrado. |

---

### RF-05 — Catálogo de Serviços

| ID | Descrição |
|----|-----------|
| RF-05.1 | O sistema deve permitir cadastrar, consultar, listar, atualizar e remover serviços do catálogo (`apiResource /api/v1/services`). |
| RF-05.2 | Um serviço pode ter itens de insumo associados (relação `service_items`). Ao adicionar um serviço a uma OS, seus itens associados devem ser incluídos automaticamente na lista de itens da OS. |

---

### RF-06 — Gestão de Itens (Estoque)

| ID | Descrição |
|----|-----------|
| RF-06.1 | O sistema deve permitir cadastrar, consultar, listar, atualizar e remover itens de estoque (`apiResource /api/v1/items`). |
| RF-06.2 | Itens devem ter um tipo (`ItemType`) e preço unitário. |

---

### RF-07 — Ciclo de Vida da Ordem de Serviço

#### RF-07.1 — Criação

| ID | Descrição |
|----|-----------|
| RF-07.1.1 | O sistema deve criar uma OS com status inicial `received`, associada a um cliente e um veículo. |
| RF-07.1.2 | Ao criar a OS, o sistema deve disparar automaticamente `notifyOrderCreated` via `MessagingServiceInterface`. |

#### RF-07.2 — Diagnóstico e composição

| ID | Descrição |
|----|-----------|
| RF-07.2.1 | O mecânico deve poder adicionar serviços do catálogo à OS nos status `received` e `in_diagnosis`. |
| RF-07.2.2 | Ao adicionar um serviço, os itens associados a ele devem ser automaticamente adicionados à OS; se o item já existir na OS, a quantidade deve ser incrementada. |
| RF-07.2.3 | O mecânico deve poder adicionar itens avulsos à OS nos status `received` e `in_diagnosis`. |
| RF-07.2.4 | O mecânico deve poder remover itens da OS nos status `received` e `in_diagnosis`. |

#### RF-07.3 — Orçamento

| ID | Descrição |
|----|-----------|
| RF-07.3.1 | O mecânico deve poder gerar o orçamento (`generate-budget`) somente quando a OS estiver em `in_diagnosis`. |
| RF-07.3.2 | O total da OS deve ser calculado como soma de `(quantidade × preço_unitário)` de serviços e itens. |
| RF-07.3.3 | Ao gerar o orçamento, a OS deve avançar para `awaiting_approval` e registrar `budget_sent_at`. |
| RF-07.3.4 | Ao gerar o orçamento, o sistema deve disparar `notifyBudgetReady` via `MessagingServiceInterface`. |

#### RF-07.4 — Decisão do cliente

| ID | Descrição |
|----|-----------|
| RF-07.4.1 | O cliente deve poder aprovar o orçamento somente quando a OS estiver em `awaiting_approval` e pertencer ao seu perfil. A OS avança para `approved`. |
| RF-07.4.2 | O cliente deve poder cancelar o orçamento somente quando a OS estiver em `awaiting_approval` e pertencer ao seu perfil. A OS avança para `cancelled` (estado terminal). |

#### RF-07.5 — Execução

| ID | Descrição |
|----|-----------|
| RF-07.5.1 | O mecânico deve poder iniciar a execução somente quando a OS estiver em `approved`. A OS avança para `in_execution`. |
| RF-07.5.2 | O mecânico deve poder finalizar a OS somente quando ela estiver em `in_execution`. A OS avança para `finalized` e registra `finalized_at`. |
| RF-07.5.3 | Ao finalizar a OS, o sistema deve disparar `notifyPickupReady` via `MessagingServiceInterface`. |

#### RF-07.6 — Pagamento e entrega

| ID | Descrição |
|----|-----------|
| RF-07.6.1 | O cliente deve poder pagar a OS somente quando ela estiver em `finalized` e ainda não tiver sido paga. O pagamento é processado via `PaymentServiceInterface`. |
| RF-07.6.2 | Após pagamento confirmado, o sistema deve registrar `paid_at` na OS. |
| RF-07.6.3 | A recepcionista deve poder registrar a entrega do veículo somente quando a OS estiver em `finalized` **e** já tiver sido paga. A OS avança para `delivered` e registra `delivered_at`. |

**Diagrama do fluxo de status:**

```
received → in_diagnosis → awaiting_approval → approved → in_execution → finalized → delivered
                                           └──────────→ cancelled
```

---

### RF-08 — Notificação de Multa por Atraso

| ID | Descrição |
|----|-----------|
| RF-08.1 | O sistema deve executar `SendFineNotificationJob` a cada hora via scheduler. |
| RF-08.2 | O job deve identificar OSs no status `finalized`, sem `paid_at`, cuja `finalized_at` seja há mais de 24 horas, e disparar `notifyPickupOverdue` para cada uma via `MessagingServiceInterface`. |

---

### RF-09 — Estatísticas

| ID | Descrição |
|----|-----------|
| RF-09.1 | O sistema deve expor `GET /api/v1/service-orders/stats` retornando tempo médio, mínimo e máximo de execução (em minutos) e total de OSs computadas, restrito a `receptionist` e `mechanic`. |

---

### RF-10 — Webhook

| ID | Descrição |
|----|-----------|
| RF-10.1 | O sistema deve receber eventos externos via `POST /webhook/messaging` (sem autenticação). |

---

### RF-11 — Documentação da API

| ID | Descrição |
|----|-----------|
| RF-11.1 | Toda rota da API deve possuir anotação OpenAPI (`@OA`) para geração automática da documentação via L5-Swagger, acessível em `/api/documentation`. |

---

## 2. Requisitos Não Funcionais

### RNF-01 — Segurança

| ID | Descrição | Justificativa técnica |
|----|-----------|----------------------|
| RNF-01.1 | Toda requisição às rotas protegidas deve exigir token Bearer válido emitido pelo Laravel Sanctum. | Sanctum emite tokens opacos simples, adequados a APIs sem a complexidade do OAuth2 (Passport). |
| RNF-01.2 | A verificação de perfil deve ocorrer no middleware `EnsureRole` a cada requisição, sem estado no cliente. | Centraliza controle de acesso; o token não carrega claims de perfil — a verificação é sempre no banco. |
| RNF-01.3 | Clientes só podem acessar OSs que lhes pertencem; a violação deve retornar HTTP 403. | Isolamento de dados por perfil; regra aplicada no controller, não apenas na query. |
| RNF-01.4 | Requests de criação devem usar `StoreXxxRequest` (campos `required`); updates devem usar `UpdateXxxRequest` (campos `sometimes`), ambos com `authorize(): true`. | Evita mass assignment e garante validação declarativa antes de chegar ao controller. |

---

### RNF-02 — Desempenho e Concorrência

| ID | Descrição | Justificativa técnica |
|----|-----------|----------------------|
| RNF-02.1 | O banco de dados deve suportar múltiplos mecânicos operando em OSs simultâneas sem bloqueio de tabela inteira. | PostgreSQL usa MVCC — leituras não bloqueiam escritas; cada mecânico trabalha em sua própria snapshot de transação. |
| RNF-02.2 | A listagem de OSs deve ser paginada (15 por página por padrão). | Protege contra queries sem limite em volumes crescentes. |
| RNF-02.3 | A suíte de testes completa deve ser executável em menos de 10 segundos. | SQLite `:memory:` elimina I/O de disco; cada execução começa do zero, sem contaminação entre testes. |

---

### RNF-03 — Integridade e Confiabilidade

| ID | Descrição | Justificativa técnica |
|----|-----------|----------------------|
| RNF-03.1 | O status da OS deve seguir máquina de estados estrita (`ServiceOrderStatus::allowedTransitions`); transições inválidas retornam HTTP 422. | Enum PHP nativo mapeado como `ENUM` no PostgreSQL — validação ocorre na camada de domínio, não por string livre. |
| RNF-03.2 | Operações de pagamento devem ser atômicas — `paid_at` só é gravado após confirmação do `PaymentServiceInterface`. | ACID completo do PostgreSQL garante que falha intermediária não deixa estado parcialmente salvo. |
| RNF-03.3 | O job `SendFineNotificationJob` deve processar OSs em atraso de forma assíncrona, sem impactar o ciclo de vida das requisições HTTP. | Driver de fila `database` armazena jobs na tabela `jobs` do PostgreSQL; inspecionável via SQL. |

---

### RNF-04 — Manutenibilidade e Qualidade de Código

| ID | Descrição | Justificativa técnica |
|----|-----------|----------------------|
| RNF-04.1 | Toda função adicionada deve ter teste unitário correspondente. Toda rota deve ter teste de feature. | Regra de projeto; garante regressão detectável antes de qualquer merge. |
| RNF-04.2 | O código deve ser analisado continuamente pelo SonarQube (code smells, duplicações, vulnerabilidades). | SonarQube consome o `coverage.xml` gerado pelo PHPUnit, dando visibilidade de cobertura por arquivo. |
| RNF-04.3 | O estilo de código deve seguir o padrão enforced pelo Laravel Pint (`./vendor/bin/pint`). | Elimina discussões de estilo em revisões; padrão consistente com a comunidade Laravel. |
| RNF-04.4 | Integrações externas (pagamento e mensageria) devem ser abstraídas por interfaces (`PaymentServiceInterface`, `MessagingServiceInterface`) e substituíveis por stubs nos testes. | Desacopla a lógica de negócio da implementação externa; testes não dependem de serviços de terceiros. |

---

### RNF-05 — Portabilidade e Ambiente

| ID | Descrição | Justificativa técnica |
|----|-----------|----------------------|
| RNF-05.1 | O ambiente completo (aplicação + banco) deve ser reproduzível com um único comando (`docker compose up -d`). | Docker Compose elimina divergências entre estações de desenvolvimento e produção ("funciona na minha máquina"). |
| RNF-05.2 | O SonarQube deve ser isolado em perfil Docker separado (`--profile sonar`) e não deve ser iniciado por padrão. | Evita consumo desnecessário de ~2 GB de RAM em desenvolvimento normal. |
| RNF-05.3 | Os testes devem rodar em SQLite `:memory:` sem necessidade de PostgreSQL no CI. | Zero infraestrutura de banco no pipeline; reduz custo e complexidade do CI. |

---

### RNF-06 — Observabilidade

| ID | Descrição | Justificativa técnica |
|----|-----------|----------------------|
| RNF-06.1 | A API deve manter relatório de cobertura de testes em `coverage.xml` (formato Clover) e resultados JUnit em `test-results.xml` para consumo pelo SonarQube. | Rastreabilidade de cobertura por arquivo; visível no dashboard do SonarQube. |
| RNF-06.2 | O endpoint `/api/v1/service-orders/stats` deve expor métricas operacionais (tempo médio/mínimo/máximo de execução) para receptionist e mechanic. | Suporte a decisões operacionais sem dependência de ferramentas externas de BI. |
| RNF-06.3 | Jobs na fila devem ser inspecionáveis diretamente via SQL na tabela `jobs` do PostgreSQL. | Driver `database` escolhido por visibilidade e ausência de infraestrutura adicional (Redis/RabbitMQ) no volume atual. |

---

### RNF-07 — Extensibilidade

| ID | Descrição | Justificativa técnica |
|----|-----------|----------------------|
| RNF-07.1 | A troca do driver de fila (`QUEUE_CONNECTION`) de `database` para Redis ou outro driver não deve exigir alteração no código de negócio. | Laravel abstrai o driver via contrato `ShouldQueue`; apenas variável de ambiente muda. |
| RNF-07.2 | A substituição dos stubs (`StubPaymentService`, `StubMessagingService`) por implementações reais deve ocorrer apenas no `AppServiceProvider`, sem alterar controllers ou jobs. | Inversão de dependência via interfaces; baixo acoplamento entre camadas. |

---

## 3. Restrições de Projeto

| Restrição | Detalhe |
|-----------|---------|
| Banco de produção | Exclusivamente PostgreSQL 16; SQLite apenas em testes |
| Busca case-insensitive | Usar `LOWER(col) LIKE ?` (compatível com SQLite nos testes); não usar `ILIKE` (exclusivo PostgreSQL) |
| Prefixo de rotas | Todas as rotas da API seguem `/api/v1/`; webhook em `/webhook/messaging` sem prefixo |
| Integrações externas | `StubPaymentService` e `StubMessagingService` — não implementar integrações reais nesta fase |
| PHP | Versão mínima 8.3 (enums nativos, typed properties, readonly) |
