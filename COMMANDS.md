# Comandos do Projeto — Workshop OS API

> O projeto roda **exclusivamente via Docker**. Não é necessário PHP, Node ou PostgreSQL instalados localmente.

---

## Subir o projeto

```bash
# Primeira vez (ou após alterar código)
docker compose up --build -d

# Subidas seguintes (sem mudança no código)
docker compose up -d
```

Na primeira execução o container automaticamente:
1. Roda as migrations
2. Popula o banco com dados de demonstração (`db:seed`)
3. Gera a documentação Swagger
4. Sobe o servidor em `http://localhost:8000`

---

## Acessar

| Recurso         | URL                                    |
|-----------------|----------------------------------------|
| API             | `http://localhost:8000/api/v1/`        |
| Swagger UI      | `http://localhost:8000/api/documentation` |

---

## Credenciais de demo

| Perfil         | E-mail                   | Senha      |
|----------------|--------------------------|------------|
| Recepcionista  | `recepcao@workshop.com`  | `password` |
| Mecânico       | `mecanico@workshop.com`  | `password` |
| Cliente 1      | `carlos@example.com`     | `password` |
| Cliente 2      | `maria@example.com`      | `password` |
| Cliente 3      | `pedro@example.com`      | `password` |

---

## Parar / resetar

```bash
# Parar os containers
docker compose down

# Reset completo (apaga banco e reconstrói tudo do zero)
docker compose down -v && docker compose up --build -d
```

---

## Logs e debug

```bash
docker compose logs -f app       # acompanhar logs em tempo real
docker compose exec app bash     # abrir shell dentro do container
```

---

## Testes

Os testes usam SQLite `:memory:` e precisam do PHP com Xdebug local (não rodam dentro do container de produção).

```bash
# Todos os testes
composer run test

# Filtrar por classe
php artisan test --filter=ServiceOrderTest

# Com cobertura (requer Xdebug)
XDEBUG_MODE=coverage composer run test:coverage
```

---

## SonarQube

```bash
# Subir SonarQube (porta 9000) — consome ~2 GB RAM
docker compose --profile sonar up -d

# Gerar cobertura e rodar análise
XDEBUG_MODE=coverage composer run test:coverage
docker compose --profile sonar run sonarscanner
```

> Acesse `http://localhost:9000` (admin / admin no primeiro acesso).
> Defina `SONAR_TOKEN` no `.env` antes de rodar o scanner.

---

## Referência rápida

| Objetivo                     | Comando                                               |
|------------------------------|-------------------------------------------------------|
| Subir (com rebuild)          | `docker compose up --build -d`                        |
| Subir (sem rebuild)          | `docker compose up -d`                                |
| Parar                        | `docker compose down`                                 |
| Reset total                  | `docker compose down -v && docker compose up --build -d` |
| Logs                         | `docker compose logs -f app`                          |
| Rodar testes                 | `composer run test`                                   |
| Testes com cobertura         | `XDEBUG_MODE=coverage composer run test:coverage`     |
| Subir SonarQube              | `docker compose --profile sonar up -d`                |
| Analisar com SonarQube       | `docker compose --profile sonar run sonarscanner`     |
