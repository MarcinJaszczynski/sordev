#!/usr/bin/env bash
# Buduje assety Vite do public/vite-dist/ (wymagane na produkcji dla panelu Filament).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [[ ! -f package.json ]]; then
  echo "Brak package.json"
  exit 1
fi

if ! command -v npm >/dev/null 2>&1; then
  echo "Błąd: zainstaluj Node.js i npm, potem: npm ci && npm run build"
  exit 1
fi

if [[ ! -d node_modules ]]; then
  echo "Instalacja zależności npm..."
  npm ci
fi

echo "Budowanie assetów (vite build)..."
npm run build

if [[ ! -f public/vite-dist/manifest.json ]]; then
  echo "Błąd: nie utworzono public/vite-dist/manifest.json"
  exit 1
fi

echo "OK: $(du -sh public/vite-dist | cut -f1) w public/vite-dist/"
