# RFC-002: Estratégia de banco de dados gerenciado

**Status**: Aceita
**Data**: 2026-09-02
**Autor(es)**: Candido, com apoio de IA

## Contexto

`evolucao_fase3` exige "Banco de Dados Gerenciado (PostgreSQL)" provisionado
via Terraform, num repositório próprio (`15SOAT-Fase1-database`, ver
[ADR-002](../adrs/adr-002-four-repo-split.md)). [RFC-001](rfc-001-cloud-strategy.md)/
[ADR-001](../adrs/adr-001-dokploy-as-cloud.md) já decidiram que a nuvem é
Dokploy — falta decidir como o Postgres é provisionado *dentro* do Dokploy,
e como o resultado (o Postgres provisionado) fica visível para o app e para
o Epic 3 (infra de app), que depende deste epic.

## Opções consideradas

1. **Recurso `Database` do Dokploy via um provider Terraform comunitário.**
   Único caminho compatível com "Terraform para provisionamento" +
   "gerenciado" dentro da estratégia Dokploy já decidida. Existem vários
   forks do provider (`vanillauys/dokploy`, `ahmedali6/dokploy`,
   `Ca-moes/dokploy`, `j0bIT/dokploy`, `TheFrozenFire/dokploy`,
   `Feng-Brasil/dokploy` — todos com poucos downloads, ecossistema
   fragmentado). Comparados os schemas reais: `vanillauys/dokploy` (v0.10.2,
   publicado 2026-09-01) é o mais completo — resource dedicado
   `dokploy_postgres` (também `mysql`/`mariadb`/`mongo`/`redis`/`libsql`),
   guias de "deploy semantics", "secrets" e "adopting an existing instance",
   testado contra Dokploy v0.30.3. Os demais forks têm schemas mais pobres
   (ex.: `j0bIT/dokploy` usa um `dokploy_database` genérico com só
   `name`/`password`/`type`/`version`, sem controle de imagem Docker, porta,
   rede).
2. **Postgres fora do Dokploy** (ex.: Neon/Supabase free tier) com Terraform
   apontando pra lá. Mais aderente à palavra "gerenciado" no sentido
   tradicional (backups automáticos, HA gerenciados pelo provedor), mas
   contradiz a decisão já tomada de Dokploy como nuvem única — introduziria
   uma terceira conta/nuvem só pro banco, sem necessidade.

## Recomendação

**Opção 1**, com o provider `vanillauys/dokploy`, pinado em `0.10.2`
(pre-1.0, breaking changes chegam em minor — ver
[ADR-005](../adrs/adr-005-dokploy-terraform-provider.md), a ser escrita no
Epic 3 quando o mesmo provider for reusado para `dokploy_application`).

### Hierarquia e ownership

Dokploy organiza recursos como **project > environment > service**. Todo
`dokploy_project` já nasce com um environment `production`. Decisão: este
repositório (`15SOAT-Fase1-database`) é quem cria o
`dokploy_project` (`15SOAT-Fase1`) — é o primeiro na ordem de dependência
dos epics (`backlog.md`: Epic 2 antes do Epic 3) — e o `dokploy_postgres`
dentro do environment `production` desse projeto. O Epic 3
(`15SOAT-Fase1-kubernetes`, recurso de app) **não cria um projeto
novo**: consome o mesmo `project_id`/`environment_id` já provisionados
aqui, para que app e banco fiquem no mesmo projeto Dokploy.

### Recurso

```hcl
resource "dokploy_postgres" "fase1" {
  name              = "15SOAT-Fase1-postgres"
  environment_id    = [for e in dokploy_project.fase1.environments : e.id if e.name == "production"][0]
  database_name     = "workshop_os"
  database_user     = "workshop_os"
  database_password = var.db_password # variável sensível, sem default
  docker_image      = "postgres:16-alpine" # mesma versão do docker-compose/k8s da Fase 2
}
```

`postgres:16-alpine` replica a versão já usada em `docker-compose.yml` e
`k8s/postgres.yaml` (Fase 2) — sem mudança de engine/versão nesta fase.

### Lacuna conhecida: nenhum backend Terraform remoto configurado

Nenhum dos módulos (`15SOAT-Fase1-database`, `15SOAT-Fase1-kubernetes`)
declara um `backend` — o state fica local. Isso é mais fundamental do que
"os dois repos não trocam outputs automaticamente" (próxima seção): mesmo
para UM repo sozinho, um `terraform apply` rodado num runner efêmero de CI
(Epic 4, `deploy.yml`) perde o state a cada run — o próximo apply não sabe
que os recursos já existem. Os workflows de deploy (Epic 4) documentam essa
ressalva explicitamente em vez de fingir que funcionam de ponta a ponta.
Candidato mais simples pra resolver, sem adicionar nuvem/serviço pago novo:
backend `pg` apontando pro próprio Postgres provisionado aqui — mas isso é
um passo futuro, não decidido nesta RFC (bootstrapping: o backend do módulo
de banco não pode depender do banco que ele mesmo cria).

### Lacuna conhecida: state não compartilhado entre repositórios

`15SOAT-Fase1-database` e `15SOAT-Fase1-kubernetes` são
repositórios (e portanto states Terraform) separados, mas o segundo precisa
do `project_id`/`environment_id` gerados pelo primeiro. Sem um backend
remoto compartilhado (Terraform Cloud, S3+DynamoDB, etc. — nenhum
provisionado ainda, fora de escopo desta RFC decidir sozinha), a
transferência desses IDs entre repos é **manual**: `terraform output` aqui
→ passado como `-var` (ou `.tfvars` não commitado) na aplicação do Epic 3.
Documentar isso explicitamente em vez de assumir automação que não existe.
Se isso incomodar na prática, uma RFC futura decide o backend remoto — não
antecipar essa decisão sem necessidade comprovada (mesmo espírito do
[ADR-004](../adrs/adr-004-communication-pattern.md)).

### Credenciais

`db_password` é variável Terraform sensível, sem valor default, nunca
commitada (`.gitignore` já cobre `*.tfvars`). `DOKPLOY_ENDPOINT` e
`DOKPLOY_API_KEY` (autenticação do provider) vêm de variáveis de ambiente,
nunca de arquivo versionado — mesma disciplina do `KUBE_CONFIG` da Fase 2.

## Consequências / próximos passos

- Epic 3 reusa `vanillauys/dokploy` (mesma versão pinada) e consome
  `project_id`/`environment_id` exportados por este módulo.
- `terraform apply` real depende de um servidor Dokploy acessível + API key
  do usuário — não executado nesta RFC/epic; o módulo fica validado
  estaticamente (`terraform validate`) até haver ambiente real disponível,
  mesmo padrão adotado na Fase 2 para `infra/cluster`/`infra/database`.
- Rate limit da API do Dokploy retorna `401` (não `429`) quando esgotado —
  documentar isso no README do repo pra não ser confundido com credencial
  errada num apply real futuro.

## Perguntas em aberto

- Backend remoto compartilhado entre `15SOAT-Fase1-database` e
  `15SOAT-Fase1-kubernetes`: decidir só se a transferência manual de
  outputs se mostrar dolorosa na prática.
