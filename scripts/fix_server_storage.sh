#!/usr/bin/env bash
# Naprawa „Please provide a valid cache path” — tworzy katalogi storage/framework na serwerze.
# Użycie (SSH na hostingu):
#   cd /home/host378742/domains/sor41.webgarage.pl/public_html
#   bash scripts/fix_server_storage.sh
set -euo pipefail

APP_ROOT="${1:-$(cd "$(dirname "$0")/.." && pwd)}"
cd "$APP_ROOT"

if [[ ! -f artisan ]]; then
  echo "Błąd: w $APP_ROOT nie ma pliku artisan. Podaj ścieżkę do katalogu Laravel:"
  echo "  bash scripts/fix_server_storage.sh /ścieżka/do/public_html"
  exit 1
fi

echo "Katalog aplikacji: $APP_ROOT"

ensure_dir() {
  mkdir -p "$1"
  chmod 775 "$1" 2>/dev/null || chmod 777 "$1" 2>/dev/null || true
}

ensure_dir storage/framework/sessions
ensure_dir storage/framework/views
ensure_dir storage/framework/cache/data
ensure_dir storage/logs
ensure_dir storage/app/public
ensure_dir storage/app/private
ensure_dir bootstrap/cache

# Pliki .gitignore (Laravel oczekuje katalogów, nie tylko ścieżek w config)
for f in storage/framework/views storage/framework/sessions storage/logs bootstrap/cache; do
  if [[ ! -f "$f/.gitignore" ]]; then
    printf '*\n!.gitignore\n' > "$f/.gitignore"
  fi
done

if [[ ! -f storage/framework/cache/data/.gitignore ]]; then
  mkdir -p storage/framework/cache/data
  printf '*\n!.gitignore\n' > storage/framework/cache/data/.gitignore
fi

chmod -R ug+rwx storage bootstrap/cache 2>/dev/null || chmod -R 777 storage bootstrap/cache 2>/dev/null || true

if [[ -n "${FIX_STORAGE_WEB_USER:-}" ]]; then
  chown -R "$FIX_STORAGE_WEB_USER" storage bootstrap/cache 2>/dev/null || true
fi

if command -v php >/dev/null 2>&1; then
  php artisan storage:link 2>/dev/null || true
  php artisan optimize:clear 2>/dev/null || true
  echo "Uruchomiono: storage:link, optimize:clear"
fi

if [[ ! -f public/vite-dist/manifest.json ]] && [[ ! -f public/build/manifest.json ]] && command -v npm >/dev/null 2>&1 && [[ -f package.json ]]; then
  echo "Brak manifestu Vite — uruchamiam npm run build..."
  (cd "$APP_ROOT" && npm ci --silent 2>/dev/null; npm run build) || echo "UWAGA: npm run build nie powiódł się."
fi

echo "OK — katalogi storage/framework utworzone."
echo "Sprawdź uprawnienia (www-data / użytkownik PHP-FPM), potem odśwież stronę."
