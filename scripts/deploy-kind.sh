#!/usr/bin/env bash
# Deploys this service to the local kind cluster (ADR-014). Called by the
# `deploy` job on the self-hosted runner; also runnable by hand.
# Usage: scripts/deploy-kind.sh ghcr.io/candidorpneto/15soat-fase1:<sha>
# Requires: platform applied (15SOAT-Fase1-kubernetes/scripts/apply-platform.sh).
set -euo pipefail

# --- per-service settings ---------------------------------------------------
namespace=os
db_rollout="statefulset/os-postgres"
deployments=(os-api os-worker)
migrate_job=os-migrate
smoke_path="/api/v1/auth/me"   # through Traefik; 401 proves gateway → service
smoke_expect=401
# ----------------------------------------------------------------------------

image="${1:?usage: $0 <image:tag>}"
cluster="${KIND_CLUSTER:-workshop-os}"
gateway="${GATEWAY_URL:-http://localhost:30080}"
root="$(cd "$(dirname "$0")/.." && pwd)"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

docker image inspect "$image" >/dev/null 2>&1 || docker pull -q "$image"
kind load docker-image "$image" --name "$cluster" >/dev/null

cp -r "$root/k8s/." "$work/"
(cd "$work" && sed -i "s|^    newName: .*|    newName: ${image%:*}|; s|^    newTag: .*|    newTag: ${image##*:}|" kustomization.yaml)
kubectl apply -k "$work"
kubectl -n "$namespace" rollout status "$db_rollout" --timeout=180s

kubectl -n "$namespace" delete job "$migrate_job" --ignore-not-found
sed "s|IMAGE|$image|" "$work/jobs/migrate.yaml" | kubectl apply -f -
kubectl -n "$namespace" wait --for=condition=complete "job/$migrate_job" --timeout=240s

for deployment in "${deployments[@]}"; do
  kubectl -n "$namespace" rollout status "deployment/$deployment" --timeout=240s
done

code="$(curl -s -o /dev/null -w '%{http_code}' -H 'Accept: application/json' "$gateway$smoke_path")"
[[ "$code" == "$smoke_expect" ]] || { echo "smoke test failed: $smoke_path -> $code (expected $smoke_expect)" >&2; exit 1; }
echo "deployed $image to $namespace; smoke $smoke_path -> $code"
