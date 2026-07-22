#!/usr/bin/env bash
# Legacy wrapper — przekierowuje do nowego modelu releases (deploy/deploy.sh).
#
# Stary sposób (git pull + maintenance mode) jest zastąpiony przez Capistrano-style deploy.
# Ten skrypt pozostaje dla kompatybilności wstecznej na serwerach bez struktury releases.
#
# Zalecane na produkcji:
#   ./deploy/deploy.sh production --ref $(git rev-parse HEAD) --source-dir . --with-migrate
#
# Użycie legacy (git pull):
#   ./scripts/deploy_production.sh [--branch main] [--with-migrate]
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
DEPLOY_SCRIPT="${ROOT}/deploy/deploy.sh"

USE_LEGACY=0
ARGS=()

while [[ $# -gt 0 ]]; do
  case "$1" in
    --legacy-git-pull)
      USE_LEGACY=1
      shift
      ;;
    -h|--help)
      cat <<EOF
Użycie: deploy_production.sh [opcje]

Domyślnie wywołuje deploy/deploy.sh (model releases) gdy struktura APP_BASE istnieje.

Opcje przekazywane do deploy.sh:
  --branch NAZWA     → --ref (checkout przed deploy) + mapowanie środowiska
  --with-migrate     → --with-migrate

Opcje legacy:
  --legacy-git-pull  Wymuś stary sposób (git pull + artisan down)

Przykład (releases):
  ./scripts/deploy_production.sh --branch main --with-migrate

Zmienne:
  APP_BASE           Domyślnie: /var/www/travel-office
  DEPLOY_ENV         staging|production (domyślnie: production)
EOF
      exit 0
      ;;
    --branch|--with-migrate)
      ARGS+=("$1")
      [[ "$1" == "--branch" ]] && { ARGS+=("${2:-}"); shift; }
      shift
      ;;
    *)
      echo "Nieznany argument: $1 (użyj --help)"
      exit 1
      ;;
  esac
done

APP_BASE="${APP_BASE:-/var/www/travel-office}"
DEPLOY_ENV="${DEPLOY_ENV:-production}"

# Mapowanie --branch na --ref
REF=""
MIGRATE=""
NEW_ARGS=()
i=0
while [[ $i -lt ${#ARGS[@]} ]]; do
  arg="${ARGS[$i]}"
  if [[ "${arg}" == "--branch" ]]; then
    REF="${ARGS[$((i + 1))]:-}"
    i=$((i + 2))
  elif [[ "${arg}" == "--with-migrate" ]]; then
    MIGRATE="--with-migrate"
    i=$((i + 1))
  else
    NEW_ARGS+=("${arg}")
    i=$((i + 1))
  fi
done

if [[ "${USE_LEGACY}" -eq 1 ]] || [[ ! -x "${DEPLOY_SCRIPT}" ]] || [[ ! -d "${APP_BASE}/releases" ]]; then
  if [[ "${USE_LEGACY}" -eq 0 ]] && [[ ! -d "${APP_BASE}/releases" ]]; then
    echo "[INFO] Brak struktury releases (${APP_BASE}/releases) — fallback do legacy git pull."
    echo "[INFO] Zobacz docs/deployment/11-vps-od-zera.md aby skonfigurować pełny deploy."
  fi
  # Wywołaj oryginalną logikę przez inline (stary skrypt)
  exec bash "${SCRIPT_DIR}/.deploy_production_legacy.sh" ${ARGS+"${ARGS[@]}"}
fi

SOURCE_DIR="${APP_BASE}/incoming/manual_$(date +%s)"
mkdir -p "${SOURCE_DIR}"

echo "[INFO] Przygotowuję paczkę release z bieżącego katalogu → ${SOURCE_DIR}"
rsync -a \
  --exclude '.git' \
  --exclude '.env' \
  --exclude 'storage' \
  --exclude 'node_modules' \
  "${ROOT}/" "${SOURCE_DIR}/"

REF="${REF:-$(git -C "${ROOT}" rev-parse HEAD 2>/dev/null || echo manual)}"

exec "${DEPLOY_SCRIPT}" "${DEPLOY_ENV}" \
  --ref "${REF}" \
  --source-dir "${SOURCE_DIR}" \
  ${MIGRATE}
