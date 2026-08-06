#!/usr/bin/env bash
# Build WordPress-ready deploy zip: contents live under dg-platform/ inside the archive.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="${1:-$(dirname "$ROOT")/dg-platform-build.zip}"
VERSION="$(grep "DG_PLATFORM_VERSION" "$ROOT/dg-platform.php" | head -1 | sed "s/.*'\\([^']*\\)'.*/\\1/")"

cd "$(dirname "$ROOT")"
rm -f "$OUT"
zip -rq "$OUT" dg-platform \
  -x "dg-platform/.git/*" \
  -x "dg-platform/.cursor/*" \
  -x "dg-platform/mcp-server/node_modules/*" \
  -x "dg-platform/**/.DS_Store"

echo "Built: $OUT (v${VERSION})"
unzip -l "$OUT" | rg "dg-platform/dg-platform\.php" || { echo "ERROR: missing dg-platform/dg-platform.php"; exit 1; }

# Remove stray deploy zips — keep only dg-platform-build.zip
find "$(dirname "$OUT")" -maxdepth 1 -name 'dg-platform-build*.zip' ! -name 'dg-platform-build.zip' -delete 2>/dev/null || true
