#!/usr/bin/env bash
# Konfiguracja środowiska deweloperskiego: import bazy z deploy/ + migracje + UFG.
# Użycie: ./scripts/setup-dev.sh [--fresh]
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

FRESH=0
for arg in "$@"; do
  case "$arg" in
    --fresh) FRESH=1 ;;
    -h|--help)
      echo "Użycie: $0 [--fresh]"
      echo "  --fresh  Usuń i ponownie zaimportuj bazę z dumpu w deploy/"
      exit 0
      ;;
    *)
      echo "Nieznany argument: $arg"
      exit 1
      ;;
  esac
done

if [[ ! -f artisan ]]; then
  echo "Uruchom z katalogu projektu (gdzie jest artisan)."
  exit 1
fi

if [[ ! -f .env ]]; then
  echo "Brak .env — skopiuj: cp .env.example .env"
  exit 1
fi

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
DB_DATABASE="${DB_DATABASE:-host378742_sor26}"
DB_USERNAME="${DB_USERNAME:-sor}"
DB_PASSWORD="${DB_PASSWORD:-sor_secret}"

if ! command -v mysql >/dev/null 2>&1; then
  echo "Błąd: brak klienta mysql. Zainstaluj MySQL/MariaDB na hoście."
  exit 1
fi

find_dump() {
  local latest=""
  shopt -s nullglob
  local candidates=(deploy/host378742_sor26_*.sql deploy/host378742_sor26.sql host378742_sor26.sql)
  for f in "${candidates[@]}"; do
    if [[ -f "$f" ]]; then
      if [[ -z "$latest" || "$f" -nt "$latest" ]]; then
        latest="$f"
      fi
    fi
  done
  shopt -u nullglob
  echo "$latest"
}

DUMP_FILE="$(find_dump)"
if [[ -z "$DUMP_FILE" ]]; then
  echo "Błąd: brak dumpu SQL (szukam deploy/host378742_sor26_*.sql)."
  exit 1
fi

mysql_root() {
  if [[ -n "${MYSQL_ROOT_PASSWORD:-}" ]]; then
    mysql -h "$DB_HOST" -P "$DB_PORT" -u root -p"$MYSQL_ROOT_PASSWORD" "$@"
    return
  fi
  if mysql -h "$DB_HOST" -P "$DB_PORT" -u root -e "SELECT 1" >/dev/null 2>&1; then
    mysql -h "$DB_HOST" -P "$DB_PORT" -u root "$@"
    return
  fi
  if sudo mysql -h "$DB_HOST" -P "$DB_PORT" -e "SELECT 1" >/dev/null 2>&1; then
    sudo mysql -h "$DB_HOST" -P "$DB_PORT" "$@"
    return
  fi
  if sudo mysql -e "SELECT 1" >/dev/null 2>&1; then
    sudo mysql "$@"
    return
  fi
  echo "Błąd: nie mogę połączyć się z MySQL jako administrator."
  echo ""
  echo "Jednorazowo uruchom (wymaga sudo):"
  echo "  sudo mysql < scripts/mysql-bootstrap.sql"
  echo ""
  echo "Potem ponów: make setup"
  exit 1
}

mysql_app() {
  mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$@"
}

echo "=== 1/7 Sprawdzanie MySQL (${DB_HOST}:${DB_PORT}) ==="
mysql_root -e "SELECT VERSION() AS version;" | tail -n 1

echo "=== 2/7 Baza i użytkownik (${DB_DATABASE} / ${DB_USERNAME}) ==="
mysql_root <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USERNAME}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
CREATE USER IF NOT EXISTS '${DB_USERNAME}'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_DATABASE}\`.* TO '${DB_USERNAME}'@'localhost';
GRANT ALL PRIVILEGES ON \`${DB_DATABASE}\`.* TO '${DB_USERNAME}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

if ! mysql_app -e "SELECT 1" >/dev/null 2>&1; then
  echo "Błąd: użytkownik ${DB_USERNAME} nie może połączyć się z MySQL."
  echo "Uruchom jednorazowo: sudo mysql < scripts/mysql-bootstrap.sql"
  exit 1
fi

DB_EXISTS=0
if mysql_app -N -e \
  "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_DATABASE}'" 2>/dev/null | grep -qv '^0$'; then
  DB_EXISTS=1
fi

if [[ "$FRESH" -eq 1 ]]; then
  echo "=== 3/7 Import dumpu (--fresh): ${DUMP_FILE} ==="
  mysql_root -e "DROP DATABASE IF EXISTS \`${DB_DATABASE}\`; CREATE DATABASE \`${DB_DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  mysql_root -e "GRANT ALL PRIVILEGES ON \`${DB_DATABASE}\`.* TO '${DB_USERNAME}'@'localhost'; GRANT ALL PRIVILEGES ON \`${DB_DATABASE}\`.* TO '${DB_USERNAME}'@'127.0.0.1'; FLUSH PRIVILEGES;"
  mysql_app "$DB_DATABASE" < "$DUMP_FILE"
elif [[ "$DB_EXISTS" -eq 0 ]]; then
  echo "=== 3/7 Import dumpu (pusta baza): ${DUMP_FILE} ==="
  mysql_app "$DB_DATABASE" < "$DUMP_FILE"
else
  echo "=== 3/7 Import dumpu — pominięty (baza ma tabele; użyj --fresh aby przeładować) ==="
fi

if [[ -z "$(read_env APP_KEY)" ]]; then
  echo "=== Generowanie APP_KEY ==="
  php artisan key:generate --force
fi

echo "=== 4/7 Synchronizacja migracji baseline (legacy dump) ==="
php artisan app:sync-migration-baseline

echo "=== 5/7 Migracje (tylko oczekujące) ==="
php artisan migrate --force

echo "=== 6/7 Moduł UFG ==="
php artisan ufg:install

echo "=== 7/7 Storage link + npm ==="
php artisan storage:link 2>/dev/null || true

if command -v npm >/dev/null 2>&1; then
  if [[ ! -d node_modules ]] || ! node -e "require.resolve('concurrently')" >/dev/null 2>&1; then
    echo "Instalacja zależności npm (npm ci)..."
    npm ci
  fi
else
  echo "Uwaga: brak npm — uruchom npm ci przed composer dev"
fi

echo ""
echo "Gotowe. Uruchom: composer dev  →  http://127.0.0.1:8000/admin"
