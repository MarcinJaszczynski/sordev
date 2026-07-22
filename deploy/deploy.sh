#!/usr/bin/env bash
# SOR41 — deployment oparty na releases (Capistrano-style).
#
# Użycie:
#   ./deploy/deploy.sh staging --ref abc1234 --with-migrate
#   ./deploy/deploy.sh production --ref abc1234
#   ./deploy/deploy.sh production --source-dir /tmp/release-upload --with-migrate
#
# GitHub Actions rsyncuje kod do releases/<name>/ przed wywołaniem tego skryptu
# LUB przekazuje --source-dir z gotowym katalogiem release.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/common.sh
source "${SCRIPT_DIR}/lib/common.sh"

ENV_NAME=""
REF=""
SOURCE_DIR=""
WITH_MIGRATE=0
SKIP_BACKUP=0
USE_MAINTENANCE=0
SKIP_COMPOSER=0

RELEASE_DIR=""
RELEASE_NAME=""
DEPLOY_FAILED=0
SWITCHED=0

usage() {
  cat <<EOF
Użycie: deploy.sh <staging|production> [opcje]

Opcje:
  --ref SHA|tag           Identyfikator wersji (skrócony SHA w nazwie release)
  --source-dir PATH       Gotowy katalog z kodem (z CI rsync). Bez tego: oczekuje istniejącego releases/<name>/
  --with-migrate          Uruchom php artisan migrate --force
  --skip-backup           Pomiń backup pre-deploy
  --maintenance           Włącz artisan down na czas deployu (wyjątki)
  --skip-composer         Pomiń composer install (gdy vendor już w paczce)
  -h, --help              Pomoc

Przykłady:
  deploy.sh staging --ref \${GITHUB_SHA} --source-dir /var/www/travel-office/incoming --with-migrate
  deploy.sh production --ref v4.2.0 --with-migrate
EOF
}

parse_args() {
  if [[ $# -lt 1 ]]; then
    usage
    exit 1
  fi

  ENV_NAME="$1"
  shift

  case "${ENV_NAME}" in
    staging|production) ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      log_error "Nieznane środowisko: ${ENV_NAME}. Użyj: staging lub production"
      exit 1
      ;;
  esac

  while [[ $# -gt 0 ]]; do
    case "$1" in
      --ref)
        REF="${2:-}"
        [[ -n "${REF}" ]] || { log_error "Brak wartości dla --ref"; exit 1; }
        shift 2
        ;;
      --source-dir)
        SOURCE_DIR="${2:-}"
        [[ -n "${SOURCE_DIR}" ]] || { log_error "Brak wartości dla --source-dir"; exit 1; }
        shift 2
        ;;
      --with-migrate) WITH_MIGRATE=1; shift ;;
      --skip-backup) SKIP_BACKUP=1; shift ;;
      --maintenance) USE_MAINTENANCE=1; shift ;;
      --skip-composer) SKIP_COMPOSER=1; shift ;;
      -h|--help) usage; exit 0 ;;
      *)
        log_error "Nieznany argument: $1"
        exit 1
        ;;
    esac
  done
}

generate_release_name() {
  local short_sha="manual"
  if [[ -n "${REF}" ]]; then
    short_sha="$(echo "${REF}" | cut -c1-7)"
  fi
  RELEASE_NAME="$(date '+%Y%m%d_%H%M%S')_${short_sha}"
  RELEASE_DIR="${RELEASES_DIR}/${RELEASE_NAME}"
}

cleanup_failed_release() {
  if [[ "${SWITCHED}" -eq 0 ]] && [[ -n "${RELEASE_DIR}" ]] && [[ -d "${RELEASE_DIR}" ]]; then
    log_warn "Usuwam nieudany release: ${RELEASE_DIR}"
    rm -rf "${RELEASE_DIR}"
  fi
}

on_error() {
  DEPLOY_FAILED=1
  log_error "Deploy przerwany (linia ${1:-?}, kod ${2:-?})"
  cleanup_failed_release
  if [[ "${USE_MAINTENANCE}" -eq 1 ]] && [[ -L "${CURRENT_LINK}" ]]; then
    (cd "${CURRENT_LINK}" && "${PHP_BIN}" artisan up) 2>/dev/null || true
  fi
  send_monitor_alert "[SOR41] Deploy FAILED — ${ENV_NAME}" "Release: ${RELEASE_NAME:-unknown}\nRef: ${REF:-unknown}\nSprawdź ${DEPLOY_LOG_FILE}"
  exit "${2:-1}"
}

prepare_release_directory() {
  if [[ -n "${SOURCE_DIR}" ]]; then
    if [[ ! -d "${SOURCE_DIR}" ]]; then
      log_error "SOURCE_DIR nie istnieje: ${SOURCE_DIR}"
      exit 1
    fi
    log_step "1/12" "Kopiuję kod z ${SOURCE_DIR} → ${RELEASE_DIR}"
    mkdir -p "${RELEASE_DIR}"
    if command -v rsync >/dev/null 2>&1; then
      rsync -a --delete \
        --exclude '.git' \
        --exclude '.env' \
        --exclude 'storage' \
        --exclude 'node_modules' \
        --exclude 'deploy/incoming' \
        "${SOURCE_DIR}/" "${RELEASE_DIR}/"
    else
      cp -a "${SOURCE_DIR}/." "${RELEASE_DIR}/"
    fi
  elif [[ -d "${RELEASE_DIR}" ]]; then
    log_step "1/12" "Używam istniejącego katalogu release: ${RELEASE_DIR}"
  else
    log_error "Brak --source-dir i katalog ${RELEASE_DIR} nie istnieje. GitHub Actions musi przesłać kod przed deployem."
    exit 1
  fi

  if [[ ! -f "${RELEASE_DIR}/artisan" ]]; then
    log_error "W release brak pliku artisan: ${RELEASE_DIR}"
    exit 1
  fi
}

run_preflight() {
  log_step "0/12" "Preflight — ${ENV_NAME}"

  load_deploy_config "${ENV_NAME}"
  require_deploy_user
  require_app_base
  require_command "${PHP_BIN}"
  require_command "${COMPOSER_BIN}"

  check_disk_space
  check_production_env

  if [[ "${ENV_NAME}" == "staging" ]] && [[ "${WITH_MIGRATE}" -eq 0 ]]; then
    WITH_MIGRATE=1
    log_info "Staging: domyślnie włączam migracje (--with-migrate)"
  fi

  generate_release_name

  if [[ "${SKIP_BACKUP}" -eq 0 ]]; then
    log_step "0b/12" "Backup pre-deploy (quick)"
    if [[ -x "${SCRIPT_DIR}/backup.sh" ]]; then
      "${SCRIPT_DIR}/backup.sh" --quick --env "${ENV_NAME}" || {
        log_error "Backup pre-deploy nie powiódł się"
        exit 1
      }
    fi
  else
    log_warn "Pominięto backup pre-deploy (--skip-backup)"
  fi
}

link_and_install() {
  log_step "2/12" "Symlinki shared (.env, storage)"
  link_shared_resources "${RELEASE_DIR}"

  log_step "3/12" "Composer install"
  if [[ "${SKIP_COMPOSER}" -eq 0 ]]; then
    cd "${RELEASE_DIR}"
    "${COMPOSER_BIN}" install --no-dev --prefer-dist --optimize-autoloader --no-interaction
  else
    log_info "Pominięto composer install (--skip-composer)"
  fi

  log_step "4/12" "Weryfikacja manifestu Vite"
  verify_vite_manifest "${RELEASE_DIR}"

  log_step "5/12" "Storage link + legacy paths"
  repair_public_storage_paths "${RELEASE_DIR}"

  if [[ -f "${RELEASE_DIR}/scripts/fix_server_storage.sh" ]]; then
    FIX_STORAGE_WEB_USER="${DEPLOY_USER}:www-data" bash "${RELEASE_DIR}/scripts/fix_server_storage.sh" "${RELEASE_DIR}" 2>/dev/null || true
  fi
}

run_migrations_and_cache() {
  cd "${RELEASE_DIR}"

  if [[ "${USE_MAINTENANCE}" -eq 1 ]]; then
    log_step "5b/12" "Maintenance mode ON"
    "${PHP_BIN}" artisan down --retry=60 || true
  fi

  log_step "6/12" "Czyszczenie cache"
  "${PHP_BIN}" artisan optimize:clear

  if [[ "${WITH_MIGRATE}" -eq 1 ]]; then
    log_step "7/12" "Migracje bazy"
    "${PHP_BIN}" artisan migrate --force
  else
    log_step "7/12" "Pomijam migracje (użyj --with-migrate)"
  fi

  log_step "8/12" "Cache produkcyjny"
  build_release_cache "${RELEASE_DIR}"
}

atomic_switch() {
  log_step "9/12" "Atomowe przełączenie current → ${RELEASE_NAME}"
  ln -sfn "${RELEASE_DIR}" "${CURRENT_LINK}"
  SWITCHED=1
  log_ok "current → ${RELEASE_DIR}"
}

post_switch() {
  log_step "10/12" "Reload usług (PHP-FPM, queue, supervisor)"
  reload_services "${RELEASE_DIR}"

  if [[ "${USE_MAINTENANCE}" -eq 1 ]]; then
    cd "${CURRENT_LINK}"
    "${PHP_BIN}" artisan up || true
  fi

  log_step "11/12" "Health check"
  if [[ -x "${SCRIPT_DIR}/lib/healthcheck.sh" ]]; then
    HEALTH_CHECK_URL="${HEALTH_CHECK_URL}" APP_ENV_NAME="${ENV_NAME}" \
      ALLOW_NON_DEPLOY_USER=1 "${SCRIPT_DIR}/lib/healthcheck.sh" || {
      log_error "Health check nie powiódł się po deployu"
      exit 1
    }
  elif command -v curl >/dev/null 2>&1; then
    curl -sf --max-time "${HEALTH_CHECK_TIMEOUT}" "${HEALTH_CHECK_URL}" >/dev/null || {
      log_error "Health check HTTP failed: ${HEALTH_CHECK_URL}"
      exit 1
    }
  fi

  log_step "12/12" "Cleanup starych releases (keep=${RELEASES_TO_KEEP})"
  cleanup_old_releases
}

main() {
  parse_args "$@"
  trap 'on_error ${LINENO} $?' ERR

  run_preflight
  prepare_release_directory
  link_and_install
  run_migrations_and_cache
  atomic_switch
  post_switch

  log_ok "Deploy zakończony: ${ENV_NAME} → ${RELEASE_NAME} (ref: ${REF:-manual})"
  send_monitor_alert "[SOR41] Deploy OK — ${ENV_NAME}" "Release: ${RELEASE_NAME}\nRef: ${REF:-manual}\nURL: ${APP_URL}"
  exit 0
}

main "$@"
