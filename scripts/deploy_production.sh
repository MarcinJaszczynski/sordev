#!/usr/bin/env bash

set -euo pipefail

BRANCH=""
WITH_MIGRATE=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --branch)
      BRANCH="${2:-}"
      if [[ -z "$BRANCH" ]]; then
        echo "Brak wartości dla --branch"
        exit 1
      fi
      shift 2
      ;;
    --with-migrate)
      WITH_MIGRATE=1
      shift
      ;;
    -h|--help)
      echo "Uzycie:"
      echo "  ./scripts/deploy_production.sh [--branch NAZWA] [--with-migrate]"
      echo
      echo "Przyklad:"
      echo "  ./scripts/deploy_production.sh --branch main --with-migrate"
      exit 0
      ;;
    *)
      echo "Nieznany argument: $1"
      exit 1
      ;;
  esac
done

if [[ ! -f artisan ]]; then
  echo "Uruchom skrypt z katalogu glownego projektu (tam gdzie jest artisan)."
  exit 1
fi

if [[ -f .env ]]; then
  if grep -Eq '^APP_DEBUG\s*=\s*true$' .env; then
    echo "UWAGA: APP_DEBUG=true w .env. Na produkcji ustaw APP_DEBUG=false."
  fi

  if grep -Eq '^DEBUGBAR_ENABLED\s*=\s*true$' .env; then
    echo "UWAGA: DEBUGBAR_ENABLED=true w .env. Na produkcji ustaw DEBUGBAR_ENABLED=false."
  fi
fi

WENT_DOWN=0

bring_up_app() {
  if [[ "$WENT_DOWN" -eq 1 ]]; then
    php artisan up || true
  fi
}

trap bring_up_app EXIT

echo "[1/7] Wlaczam maintenance mode"
php artisan down || true
WENT_DOWN=1

echo "[2/7] Aktualizuje kod"
if command -v git >/dev/null 2>&1; then
  git fetch --all --prune
  if [[ -n "$BRANCH" ]]; then
    git checkout "$BRANCH"
  fi
  git pull --ff-only
else
  echo "Git nie jest dostepny - pomijam aktualizacje kodu."
fi

echo "[3/7] Instaluje zaleznosci produkcyjne"
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

echo "[4/7] Czyszcze cache Laravel"
php artisan optimize:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear

if [[ "$WITH_MIGRATE" -eq 1 ]]; then
  echo "[5/7] Uruchamiam migracje"
  php artisan migrate --force
else
  echo "[5/7] Pomijam migracje (uzyj --with-migrate, jesli potrzebne)"
fi

echo "[6/7] Buduje cache produkcyjny"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "[7/7] Wylaczam maintenance mode"
php artisan up
WENT_DOWN=0

echo "Deploy zakonczony powodzeniem."