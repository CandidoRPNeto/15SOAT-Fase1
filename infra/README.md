# Infraestrutura como Código (Terraform)

Provisiona o ambiente local (kind) usado nesta fase. Cloud fica documentado
como próximo passo, não implementado — ver `docs/specs/fase2/plan.md` §3.3.

## Módulos e recursos criados

### `infra/cluster/`

Provisiona o cluster Kubernetes local via [kind](https://kind.sigs.k8s.io/)
(Kubernetes-in-Docker), usando o provider Terraform
[`tehcyx/kind`](https://registry.terraform.io/providers/tehcyx/kind/latest):

- 1 node control-plane + 1 node worker
- Porta do host `30080` mapeada para o NodePort da API (`k8s/service.yaml`)
- Kubeconfig escrito em `~/.kube/workshop-os-config` (configurável via
  `kubeconfig_path`)

### `infra/database/`

Provisiona o banco de dados dentro do cluster criado acima, usando o
provider oficial `hashicorp/kubernetes` (recurso `kubernetes_manifest`).
Em vez de redefinir os recursos em HCL, este módulo **reaproveita os
manifestos existentes em `/k8s`** como única fonte de verdade:

- `k8s/configmap.yaml` — variáveis não sensíveis
- `k8s/secret.yaml` — template de secrets (o Postgres depende dele para
  `DB_PASSWORD`; substitua os placeholders antes de aplicar em qualquer
  ambiente que não seja descartável)
- `k8s/postgres.yaml` — PVC + Deployment + Service do Postgres

O Deployment e o Service da **aplicação** (`k8s/deployment.yaml`,
`k8s/service.yaml`, `k8s/hpa.yaml`, `k8s/migrate-job.yaml`) **não** são
geridos por este módulo — ficam a cargo de `kubectl apply -f k8s/` (manual
ou via pipeline de CI/CD, ver M6). Terraform aqui cobre só infraestrutura
(cluster + banco); o deploy da aplicação em si é responsabilidade do
CI/CD, conforme `docs/specs/fase2/plan.md` §6.

## Pré-requisitos

- Docker rodando localmente (kind cria o cluster como containers Docker)
- Terraform `>= 1.5`
- Acesso à internet no `terraform init` (baixa os providers e a imagem do
  node kind na primeira execução)

## Como aplicar

```bash
# 1. Cluster
cd infra/cluster
terraform init
terraform apply

# 2. Banco (depende do cluster acima já estar de pé)
cd ../database
terraform init
terraform apply

# 3. Aplicação (fora do escopo do Terraform, ver seção acima)
kubectl --kubeconfig ~/.kube/workshop-os-config apply -f ../../k8s/
```

## Como destruir

```bash
cd infra/database && terraform destroy
cd ../cluster && terraform destroy
```

`infra/database` precisa ser destruído antes de `infra/cluster` — ao
destruir o cluster primeiro, o state do módulo `database` fica referenciando
recursos que não existem mais.
