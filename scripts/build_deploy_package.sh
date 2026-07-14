#!/usr/bin/env bash
# Buduje paczkę ZIP: aplikacja (z vendor) + storage + dump MySQL + instrukcja wdrożenia.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [[ ! -f artisan ]]; then
  echo "Uruchom z katalogu projektu (gdzie jest artisan)."
  exit 1
fi

if [[ ! -f .env ]]; then
  echo "Brak pliku .env — potrzebny do eksportu bazy."
  exit 1
fi

# Wczytaj DB_* z .env
read_env() {
  local key="$1"
  grep -E "^${key}=" .env 2>/dev/null | head -1 | cut -d= -f2- | tr -d '\r' | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"
}

DB_HOST="$(read_env DB_HOST)"
DB_PORT="$(read_env DB_PORT)"
DB_DATABASE="$(read_env DB_DATABASE)"
DB_USERNAME="$(read_env DB_USERNAME)"
DB_PASSWORD="$(read_env DB_PASSWORD)"

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"

TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
DEPLOY_DIR="$ROOT/deploy"
BUILD_DIR="$DEPLOY_DIR/build-$TIMESTAMP"
ZIP_PATH="$DEPLOY_DIR/sor41-wdrozenie-${TIMESTAMP}.zip"

mkdir -p "$BUILD_DIR"
mkdir -p "$DEPLOY_DIR"

echo "=== Eksport bazy: ${DB_DATABASE} @ ${DB_HOST}:${DB_PORT} ==="
if ! command -v mysqldump >/dev/null 2>&1; then
  echo "Błąd: brak mysqldump."
  exit 1
fi

export MYSQL_PWD="${DB_PASSWORD}"
mysqldump \
  --host="${DB_HOST}" \
  --port="${DB_PORT}" \
  --user="${DB_USERNAME}" \
  --single-transaction \
  --routines \
  --triggers \
  --quick \
  "${DB_DATABASE}" > "$BUILD_DIR/database.sql"
unset MYSQL_PWD

DB_SIZE="$(du -h "$BUILD_DIR/database.sql" | cut -f1)"
echo "  → database.sql (${DB_SIZE})"

echo "=== Budowanie assetów frontend (Vite) ==="
if command -v npm >/dev/null 2>&1; then
  if [[ ! -d node_modules ]]; then
    npm ci --silent 2>/dev/null || npm install --silent 2>/dev/null || true
  fi
  npm run build 2>/dev/null || echo "  ⚠ npm run build nie powiódł się — na serwerze uruchom: npm ci && npm run build"
  if [[ -f public/vite-dist/manifest.json ]]; then
    echo "  → public/vite-dist/manifest.json OK"
  elif [[ -f public/build/manifest.json ]]; then
    echo "  → public/build/manifest.json OK (legacy)"
  else
    echo "  ⚠ Brak manifestu Vite — po wdrożeniu uruchom scripts/build_frontend.sh"
  fi
else
  echo "  ⚠ Brak npm — po wdrożeniu uruchom scripts/build_frontend.sh lub wgraj public/vite-dist/"
fi

echo "=== Kopiowanie aplikacji ==="
RSYNC_EXCLUDES=(
  --exclude '.git'
  --exclude '.env'
  --exclude '.env.*'
  --exclude 'node_modules'
  --exclude 'deploy'
  --exclude '/build'
  --exclude '.local'
  --exclude 'dumps'
  --exclude 'storage/backups'
  --exclude 'storage/logs'
  --exclude 'storage/framework/cache'
  --exclude 'storage/framework/sessions'
  --exclude 'storage/framework/views'
  --exclude 'storage/debugbar'
  --exclude 'bootstrap/cache/*.php'
  --exclude 'mergingSOR'
  --exclude 'sorstary'
  --exclude 'host378742_sor26.sql'
  --exclude 'pliki'
  --exclude '.idea'
  --exclude '.vscode'
  --exclude 'tests'
  --exclude 'phpunit.xml'
  --exclude 'Zrzut*.png'
  --exclude '*.zip'
  --exclude '*.sql'
  --exclude '*.log'
  --exclude 'migrate_debug.log'
)

rsync -a "${RSYNC_EXCLUDES[@]}" "$ROOT/" "$BUILD_DIR/app/"

echo "=== Szkielet storage/framework (wymagany na serwerze) ==="
APP="$BUILD_DIR/app"
for dir in \
  storage/framework/sessions \
  storage/framework/views \
  storage/framework/cache/data \
  storage/logs \
  bootstrap/cache; do
  mkdir -p "$APP/$dir"
  if [[ ! -f "$APP/$dir/.gitignore" ]]; then
    printf '*\n!.gitignore\n' > "$APP/$dir/.gitignore"
  fi
done

# Szablon .env na produkcję (bez haseł z dev — uzupełnij na serwerze)
if [[ -f .env.example ]]; then
  cp .env.example "$BUILD_DIR/env.szablon.txt"
else
  cp .env "$BUILD_DIR/env.szablon.txt"
fi

sed -i \
  -e 's/^APP_ENV=.*/APP_ENV=production/' \
  -e 's/^APP_DEBUG=.*/APP_DEBUG=false/' \
  -e 's/^LOG_LEVEL=.*/LOG_LEVEL=error/' \
  -e 's/^DEBUGBAR_ENABLED=.*/DEBUGBAR_ENABLED=false/' \
  "$BUILD_DIR/env.szablon.txt" 2>/dev/null || true

cat > "$BUILD_DIR/WDROZENIE.txt" <<'EOF'
SOR 4.1 — instrukcja wdrożenia paczki
=====================================

Zawartość archiwum:
  app/              — kod Laravel (vendor w środku)
  database.sql      — pełny zrzut bazy MySQL
  env.szablon.txt   — szablon .env (skopiuj jako app/.env i uzupełnij)
  WDROZENIE.txt     — ten plik

1) Wymagania serwera
   - PHP >= 8.2 (rozszerzenia: mbstring, openssl, pdo_mysql, tokenizer, xml, ctype, json, fileinfo, gd lub imagick)
   - MySQL 8.x / MariaDB 10.6+
   - Composer (opcjonalnie — vendor jest w paczce)
   - DocumentRoot wskazujący na: app/public

2) Rozpakowanie
   unzip sor41-wdrozenie-*.zip -d /ścieżka/na/serwerze/
   # Struktura docelowa np. /var/www/sor/app/public

3) Baza danych
   Utwórz bazę i użytkownika, potem:
   mysql -h HOST -u USER -p NAZWA_BAZY < database.sql

4) Konfiguracja .env
   cp env.szablon.txt app/.env
   nano app/.env
   Ustaw m.in.: APP_URL, DB_*, APP_KEY (lub: cd app && php artisan key:generate)

5) Katalogi cache (OBOWIĄZKOWE — bez tego: „Please provide a valid cache path”)
   cd app
   bash scripts/fix_server_storage.sh
   # lub ręcznie:
   # mkdir -p storage/framework/{sessions,views,cache/data} storage/logs bootstrap/cache
   # chmod -R 775 storage bootstrap/cache

6) Uprawnienia
   chmod -R ug+rwx storage bootstrap/cache
   chown -R www-data:www-data storage bootstrap/cache   # dostosuj użytkownik WWW

7) Storage publiczny
   cd app
   php artisan storage:link

8) Cache produkcyjny
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache

9) Moduł UFG (jeśli tabela contracts nie istnieje po imporcie)
   php artisan ufg:install
   UWAGA: NIE uruchamiaj pełnego „php artisan migrate” na starej bazie z danymi!

10) Cron (opcjonalnie)
   * * * * * cd /ścieżka/app && php artisan schedule:run >> /dev/null 2>&1

11) Kolejka (jeśli QUEUE_CONNECTION=database)
    php artisan queue:work --daemon

Aktualizacja w przyszłości: scripts/deploy_production.sh (na serwerze, z gita) lub nowa paczka ZIP.
EOF

cat > "$BUILD_DIR/manifest.json" <<MANIFEST
{
  "created_at": "$(date -Iseconds)",
  "database": "${DB_DATABASE}",
  "db_host": "${DB_HOST}",
  "db_port": "${DB_PORT}",
  "laravel": "$(php artisan --version 2>/dev/null | head -1 || echo unknown)",
  "php": "$(php -r 'echo PHP_VERSION;')"
}
MANIFEST

echo "=== Pakowanie ZIP ==="
rm -f "$ZIP_PATH"
(
  cd "$BUILD_DIR"
  zip -r -q "$ZIP_PATH" . -x "*.DS_Store" -x "*__MACOSX*"
)

rm -rf "$BUILD_DIR"

ZIP_SIZE="$(du -h "$ZIP_PATH" | cut -f1)"
echo ""
echo "Gotowe: $ZIP_PATH ($ZIP_SIZE)"
echo "SHA256: $(sha256sum "$ZIP_PATH" | cut -d' ' -f1)"
