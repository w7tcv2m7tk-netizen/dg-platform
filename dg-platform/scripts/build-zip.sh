#!/usr/bin/env bash
# Build WordPress-ready deploy zip: contents live under dg-platform/ inside the archive.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="${1:-$(dirname "$ROOT")/dg-platform-build.zip}"

cd "$(dirname "$ROOT")"
rm -f "$OUT"
zip -rq "$OUT" dg-platform \
  -x "dg-platform/.git/*" \
  -x "dg-platform/.cursor/*" \
  -x "dg-platform/mcp-server/node_modules/*" \
  -x "dg-platform/**/.DS_Store"

echo "Built: $OUT"
unzip -l "$OUT" | rg "dg-platform/dg-platform\.php" || { echo "ERROR: missing dg-platform/dg-platform.php"; exit 1; }
