# k8s/ — OS Service (Fase 4)

Manifestos do OS Service no namespace `os` do cluster kind local
(`workshop-os`). Plataforma compartilhada (Traefik + ForwardAuth, RabbitMQ,
Secrets) fica em
[`15SOAT-Fase1-kubernetes`](https://github.com/CandidoRPNeto/15SOAT-Fase1-kubernetes)
(`scripts/apply-platform.sh`).

| Arquivo | Conteúdo |
|---|---|
| `configmap.yaml` | `os-config` (DB, RabbitMQ, saga, Datadog) |
| `postgres.yaml` | StatefulSet `os-postgres` — banco próprio do serviço |
| `api.yaml` | Deployment + Service + HPA `os-api` |
| `worker.yaml` | Deployment `os-worker`: `bus:work` (saga) + `schedule:work` (watchdog) |
| `ingress.yaml` | Rota catch-all `/` no Traefik |
| `jobs/migrate.yaml` | Job `os-migrate` (recriado a cada deploy) |
| `secret.yaml` | Só template — o Secret real vem de `bootstrap-secrets.sh` |

Deploy (o mesmo que o job `deploy` do CI roda no runner self-hosted, ADR-014):

```bash
export KUBECONFIG=~/.kube/workshop-os-config
scripts/deploy-kind.sh ghcr.io/candidorpneto/15soat-fase1:<tag>
```

Nunca aplique `secret.yaml`: os placeholders `CHANGE_ME` sobrescreveriam o Secret real.
