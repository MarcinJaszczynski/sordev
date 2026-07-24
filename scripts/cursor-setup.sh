#!/usr/bin/env bash
# Bootstrap środowiska Cursor dla SOR41
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "==> SOR41 — konfiguracja Cursor"
echo "    Katalog: $ROOT"

# 1. MCP projektu (gitignored — tylko lokalnie)
if [[ ! -f .cursor/mcp.json ]]; then
  cp .cursor/mcp.json.example .cursor/mcp.json
  echo "    Utworzono .cursor/mcp.json z przykładu"
else
  echo "    .cursor/mcp.json już istnieje"
fi

# 2. Opcjonalnie: globalny MCP (Cursor czyta też ~/.cursor/mcp.json)
GLOBAL_MCP="$HOME/.cursor/mcp.json"
if [[ ! -f "$GLOBAL_MCP" ]]; then
  mkdir -p "$HOME/.cursor"
  cp .cursor/mcp.json "$GLOBAL_MCP"
  echo "    Skopiowano MCP do $GLOBAL_MCP"
else
  echo "    $GLOBAL_MCP już istnieje — nie nadpisuję"
fi

# 3. Playwright dla console-audit
if [[ -f package.json ]] && command -v npm >/dev/null 2>&1; then
  if [[ ! -d node_modules/@playwright/test ]]; then
    echo "    npm ci..."
    npm ci --silent 2>/dev/null || npm install --silent
  fi
  echo "    Playwright chromium..."
  npx playwright install chromium 2>/dev/null || npm run console-audit:install 2>/dev/null || true
fi

# 4. Weryfikacja dev
echo ""
echo "==> Sprawdzenie środowiska"
chmod +x scripts/php-bin.sh 2>/dev/null || true
if [[ -x scripts/php-bin.sh ]]; then
  PHP="$(./scripts/php-bin.sh 2>/dev/null || true)"
  if [[ -n "$PHP" ]]; then
    "$PHP" -v | head -1
  else
    php -v | head -1
  fi
else
  php -v | head -1
fi
if ! command -v php84 >/dev/null 2>&1; then
  echo "    PHP 8.4: brak php84 — uruchom: sudo make install-php84"
fi
composer -V 2>/dev/null | head -1 || true
node -v 2>/dev/null || echo "    Node: brak"
echo ""

if [[ -f .env ]]; then
  echo "    .env: OK"
else
  echo "    .env: BRAK — uruchom: cp .env.example .env && make setup"
fi

echo ""
echo "==> Gotowe. W Cursorze:"
echo "    1. Otwórz folder: $ROOT"
echo "    2. Settings → MCP → włącz sor41-mysql, sor41-files, playwright"
echo "    3. Ctrl+Shift+P → 'Extensions: Show Recommended Extensions' → Install All"
echo "    4. Przeczytaj: docs/WORKSPACE.md i AGENTS.md"
echo ""
echo "    Dev: composer dev  →  http://127.0.0.1:8000/admin"
