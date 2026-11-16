#!/usr/bin/env bash
# Renders every Fase 4 Mermaid diagram to SVG and PNG (for the PDF and READMEs).
# Uses a local Chrome/Chromium (sandbox on) instead of a puppeteer download.
# Usage: scripts/render-diagrams.sh
set -euo pipefail
dir="$(cd "$(dirname "$0")/.." && pwd)/docs/architecture/diagrams/fase4"
cfg="$(mktemp)"; trap 'rm -f "$cfg"' EXIT
browser="${PUPPETEER_EXECUTABLE_PATH:-$(command -v chromium || command -v google-chrome-stable || true)}"
if [[ -n "$browser" ]]; then echo "{\"executablePath\":\"$browser\"}" > "$cfg"; else echo '{}' > "$cfg"; fi
for src in "$dir"/*.mmd; do
  base="${src%.mmd}"
  npx -y @mermaid-js/mermaid-cli@11.4.2 -q -p "$cfg" -i "$src" -o "$base.svg" -b white
  npx -y @mermaid-js/mermaid-cli@11.4.2 -q -p "$cfg" -i "$src" -o "$base.png" -b white -s 2
  echo "rendered $(basename "$base")"
done
