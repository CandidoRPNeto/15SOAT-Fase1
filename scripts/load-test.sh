#!/usr/bin/env bash
# Gera carga real na API (login + criação de OS) para demonstrar, no vídeo
# da Fase 2, consumo das APIs e escalabilidade automática (HPA) ao vivo.
#
# Uso:
#   ./scripts/load-test.sh [BASE_URL] [CONCURRENCY] [DURATION]
#
# Exemplo contra o cluster kind provisionado via Terraform (k8s/service.yaml,
# NodePort 30080):
#   ./scripts/load-test.sh http://localhost:30080 50 90s
#
# Em outro terminal, para acompanhar o scaling:
#   kubectl get hpa workshop-os-app -w
#   kubectl get pods -l app=workshop-os-app -w
set -euo pipefail

BASE_URL="${1:-http://localhost:30080}"
CONCURRENCY="${2:-50}"
DURATION="${3:-90s}"

command -v hey >/dev/null || { echo "Instale 'hey' (https://github.com/rakyll/hey) antes de rodar este script." >&2; exit 1; }
command -v jq >/dev/null || { echo "Instale 'jq' antes de rodar este script." >&2; exit 1; }

echo "==> Login como recepcionista"
TOKEN=$(curl -sf -X POST "$BASE_URL/api/v1/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"recepcao@workshop.com","password":"password"}' | jq -r '.token')

[ -n "$TOKEN" ] && [ "$TOKEN" != "null" ] || { echo "Falha no login — token vazio." >&2; exit 1; }

echo "==> Buscando client_id e vehicle_id de exemplo (dados do seed)"
CLIENT_ID=$(curl -sf "$BASE_URL/api/v1/clients" -H "Authorization: Bearer $TOKEN" | jq -r '.data[0].id')
VEHICLE_ID=$(curl -sf "$BASE_URL/api/v1/vehicles" -H "Authorization: Bearer $TOKEN" | jq -r '.data[0].id')
SERVICE_ID=$(curl -sf "$BASE_URL/api/v1/services" -H "Authorization: Bearer $TOKEN" | jq -r '.data[0].id')

echo "    client_id=$CLIENT_ID vehicle_id=$VEHICLE_ID service_id=$SERVICE_ID"

PAYLOAD_FILE="$(mktemp)"
trap 'rm -f "$PAYLOAD_FILE"' EXIT
jq -n --argjson client_id "$CLIENT_ID" --argjson vehicle_id "$VEHICLE_ID" --argjson service_id "$SERVICE_ID" \
  '{client_id: $client_id, vehicle_id: $vehicle_id, notes: "OS gerada por load-test.sh", services: [{service_id: $service_id, quantity: 1}]}' \
  > "$PAYLOAD_FILE"

echo "==> Disparando carga: $CONCURRENCY conexões simultâneas por $DURATION contra POST /api/v1/service-orders"
echo "    (acompanhe em outro terminal: kubectl get hpa workshop-os-app -w)"

hey -z "$DURATION" -c "$CONCURRENCY" -m POST \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -D "$PAYLOAD_FILE" \
  "$BASE_URL/api/v1/service-orders"
