#!/usr/bin/env bash
# Wspólne funkcje dla deploy/rollback/backup SOR41.
set -euo pipefail

DEPLOY_LIB_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEPLOY_ROOT="$(cd "${DEPLOY_LIB_DIR}/.." && pwd)"

# Kolory (tylko gdy stdout jest TTY)
if [[ -t 1 ]]; then
  COLOR_RESET='\033[0m'
  COLOR_RED='\033[0;31m'
  COLOR_GREEN='\033[0;32m'
  COLOR_YELLOW='\033[0;33m'
  COLOR_BLUE='\033[0;34m'
  COLOR_CYAN='\033[0;36m'
  COLOR_BOLD='\033[1m'
else
  COLOR_RESET=''
  COLOR_RED=''
  COLOR_GREEN=''
  COLOR_YELLOW=''
  COLOR_BLUE=''
  COLOR_CYAN=''
  COLOR_BOLD=''
fi

log_info() {
  local msg="$1"
  echo -e "${COLOR_BLUE}[INFO]${COLOR_RESET} ${msg}"
  deploy_log "INFO" "${msg}"
}

log_ok() {
  local msg="$1"
  echo -e "${COLOR_GREEN}[OK]${COLOR_RESET} ${msg}"
  deploy_log "OK" "${msg}"
}

log_warn() {
  local msg="$1"
  echo -e "${COLOR_YELLOW}[WARN]${COLOR_RESET} ${msg}" >&2
  deploy_log "WARN" "${msg}"
}

log_error() {
  local msg="$1"
  echo -e "${COLOR_RED}[ERROR]${COLOR_RESET} ${msg}" >&2
  deploy_log "ERROR" "${msg}"
}

log_step() {
  local step="$1"
  local msg="$2"
  echo -e "${COLOR_CYAN}${COLOR_BOLD}[${step}]${COLOR_RESET} ${msg}"
  deploy_log "STEP" "[${step}] ${msg}"
}

deploy_log() {
  local level="$1"
  local msg="$2"
  if [[ -n "${DEPLOY_LOG_FILE:-}" ]]; then
    mkdir -p "$(dirname "${DEPLOY_LOG_FILE}")"
    printf '%s [%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "${level}" "${msg}" >> "${DEPLOY_LOG_FILE}"
  fi
}

load_deploy_config() {
  local env_name="${1:-}"
  local config_file="${DEPLOY_CONFIG_FILE:-${DEPLOY_ROOT}/config/app.env}"

  if [[ -f "${config_file}" ]]; then
    # shellcheck disable=SC1090
    source "${config_file}"
  elif [[ -f "${DEPLOY_ROOT}/config/app.env.example" ]]; then
    log_warn "Brak ${config_file} — używam wartości domyślnych. Skopiuj deploy/config/app.env.example → deploy/config/app.env"
  fi

  APP_BASE="${APP_BASE:-/var/www/travel-office}"
  APP_ENV_NAME="${APP_ENV_NAME:-${env_name:-production}}"
  APP_URL="${APP_URL:-https://example.com}"
  DEPLOY_USER="${DEPLOY_USER:-deploy}"
  PHP_BIN="${PHP_BIN:-php}"
  PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.4-fpm}"
  COMPOSER_BIN="${COMPOSER_BIN:-composer}"
  RELEASES_TO_KEEP="${RELEASES_TO_KEEP:-5}"
  MIN_FREE_DISK_GB="${MIN_FREE_DISK_GB:-2}"
  HEALTH_CHECK_URL="${HEALTH_CHECK_URL:-${APP_URL}/up}"
  HEALTH_CHECK_TIMEOUT="${HEALTH_CHECK_TIMEOUT:-30}"
  SUPERVISOR_WORKER_GROUP="${SUPERVISOR_WORKER_GROUP:-travel-office-worker:*}"
  MONITOR_EMAIL="${MONITOR_EMAIL:-}"
  BACKUP_KEEP_MYSQL="${BACKUP_KEEP_MYSQL:-14}"
  BACKUP_KEEP_STORAGE="${BACKUP_KEEP_STORAGE:-7}"
  BACKUP_KEEP_ENV="${BACKUP_KEEP_ENV:-30}"
  BACKUP_KEEP_CONFIG="${BACKUP_KEEP_CONFIG:-7}"

  SHARED_DIR="${APP_BASE}/shared"
  RELEASES_DIR="${APP_BASE}/releases"
  CURRENT_LINK="${APP_BASE}/current"
  BACKUPS_DIR="${APP_BASE}/backups"
  DEPLOY_LOG_FILE="${SHARED_DIR}/logs/deploy.log"
  BACKUP_LOG_FILE="${SHARED_DIR}/logs/backup.log"
}

require_command() {
  local cmd="$1"
  if ! command -v "${cmd}" >/dev/null 2>&1; then
    log_error "Brak wymaganego polecenia: ${cmd}"
    exit 1
  fi
}

require_deploy_user() {
  if [[ "$(id -un)" != "${DEPLOY_USER}" ]] && [[ "${ALLOW_NON_DEPLOY_USER:-0}" != "1" ]]; then
    log_warn "Skrypt uruchomiony jako $(id -un), oczekiwany użytkownik: ${DEPLOY_USER}. Ustaw ALLOW_NON_DEPLOY_USER=1 aby kontynuować."
    if [[ "${ALLOW_NON_DEPLOY_USER:-0}" != "1" ]]; then
      exit 1
    fi
  fi
}

require_app_base() {
  if [[ ! -d "${APP_BASE}" ]]; then
    log_error "Katalog APP_BASE nie istnieje: ${APP_BASE}"
    exit 1
  fi

  mkdir -p "${SHARED_DIR}" "${RELEASES_DIR}" "${BACKUPS_DIR}/mysql" "${BACKUPS_DIR}/storage" \
    "${BACKUPS_DIR}/env" "${BACKUPS_DIR}/config/nginx" "${BACKUPS_DIR}/config/supervisor" \
    "${SHARED_DIR}/logs/monitoring"
}

check_disk_space() {
  local min_gb="${MIN_FREE_DISK_GB}"
  local avail_kb
  avail_kb="$(df -Pk "${APP_BASE}" | awk 'NR==2 {print $4}')"
  local avail_gb=$((avail_kb / 1024 / 1024))

  if [[ "${avail_gb}" -lt "${min_gb}" ]]; then
    log_error "Za mało wolnego miejsca na ${APP_BASE}: ${avail_gb} GB (wymagane min. ${min_gb} GB)"
    exit 1
  fi

  log_ok "Wolne miejsce: ${avail_gb} GB"
}

check_production_env() {
  local env_file="${SHARED_DIR}/.env"
  if [[ ! -f "${env_file}" ]]; then
    log_error "Brak pliku ${env_file}. Utwórz shared/.env przed pierwszym deployem."
    exit 1
  fi

  if grep -Eq '^APP_DEBUG\s*=\s*true' "${env_file}"; then
    log_error "APP_DEBUG=true w ${env_file}. Na produkcji ustaw APP_DEBUG=false."
    exit 1
  fi

  if grep -Eq '^DEBUGBAR_ENABLED\s*=\s*true' "${env_file}"; then
    log_warn "DEBUGBAR_ENABLED=true w ${env_file}. Zalecane: false na produkcji."
  fi
}

read_env_value() {
  local key="$1"
  local file="${2:-${SHARED_DIR}/.env}"
  if [[ ! -f "${file}" ]]; then
    return 1
  fi
  grep -E "^${key}=" "${file}" 2>/dev/null | head -1 | cut -d= -f2- | tr -d '\r' | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"
}

ensure_laravel_storage_paths() {
  local app_root="$1"
  mkdir -p "${app_root}/storage/framework/sessions"
  mkdir -p "${app_root}/storage/framework/views"
  mkdir -p "${app_root}/storage/framework/cache/data"
  mkdir -p "${app_root}/storage/logs"
  mkdir -p "${app_root}/storage/app/public"
  mkdir -p "${app_root}/bootstrap/cache"

  for dir in storage/framework/views storage/framework/sessions storage/logs bootstrap/cache; do
    if [[ ! -f "${app_root}/${dir}/.gitignore" ]]; then
      printf '*\n!.gitignore\n' > "${app_root}/${dir}/.gitignore"
    fi
  done
}

link_shared_resources() {
  local release_dir="$1"

  ln -sfn "${SHARED_DIR}/.env" "${release_dir}/.env"
  ln -sfn "${SHARED_DIR}/storage" "${release_dir}/storage"

  ensure_laravel_storage_paths "${SHARED_DIR}"
}

repair_public_storage_paths() {
  local release_dir="$1"
  local legacy_root="${SHARED_DIR}/storage/app"
  local public_root="${SHARED_DIR}/storage/app/public"

  mkdir -p "${public_root}"

  cd "${release_dir}"

  if [[ -L public/storage ]]; then
    log_info "public/storage -> $(readlink public/storage)"
  elif [[ -e public/storage ]]; then
    log_warn "public/storage istnieje, ale nie jest symlinkiem."
  else
    "${PHP_BIN}" artisan storage:link || true
  fi

  local legacy_paths=(
    "event-templates"
    "program_points"
    "turysci.jpg"
  )

  for rel in "${legacy_paths[@]}"; do
    local src="${legacy_root}/${rel}"
    local dst="${public_root}/${rel}"

    if [[ -d "${src}" ]]; then
      mkdir -p "${dst}"
      if command -v rsync >/dev/null 2>&1; then
        rsync -a --ignore-existing "${src}/" "${dst}/"
      else
        cp -an "${src}/." "${dst}/" 2>/dev/null || true
      fi
      log_info "Skopiowano katalog legacy: ${src} -> ${dst}"
    elif [[ -f "${src}" ]] && [[ ! -f "${dst}" ]]; then
      mkdir -p "$(dirname "${dst}")"
      cp -a "${src}" "${dst}"
      log_info "Skopiowano plik legacy: ${src} -> ${dst}"
    fi
  done
}

reload_services() {
  local release_dir="$1"
  cd "${release_dir}"

  if command -v sudo >/dev/null 2>&1; then
    sudo systemctl reload "${PHP_FPM_SERVICE}" 2>/dev/null || sudo systemctl restart "${PHP_FPM_SERVICE}" 2>/dev/null || log_warn "Nie udało się przeładować ${PHP_FPM_SERVICE}"
  else
    log_warn "Brak sudo — pomiń reload PHP-FPM (zrób ręcznie: systemctl reload ${PHP_FPM_SERVICE})"
  fi

  "${PHP_BIN}" artisan queue:restart 2>/dev/null || true

  if "${PHP_BIN}" artisan list 2>/dev/null | grep -q '^  horizon$'; then
    "${PHP_BIN}" artisan horizon:terminate 2>/dev/null || true
    log_info "Horizon: wysłano sygnał terminate"
  fi

  if command -v supervisorctl >/dev/null 2>&1; then
    supervisorctl restart "${SUPERVISOR_WORKER_GROUP}" 2>/dev/null || log_warn "supervisorctl restart ${SUPERVISOR_WORKER_GROUP} nie powiódł się"
  fi
}

build_release_cache() {
  local release_dir="$1"
  cd "${release_dir}"

  "${PHP_BIN}" artisan optimize:clear
  "${PHP_BIN}" artisan config:cache
  "${PHP_BIN}" artisan route:cache
  "${PHP_BIN}" artisan view:cache
  "${PHP_BIN}" artisan event:cache 2>/dev/null || true
}

get_current_release() {
  if [[ -L "${CURRENT_LINK}" ]]; then
    readlink -f "${CURRENT_LINK}"
  fi
}

list_releases_sorted() {
  if [[ ! -d "${RELEASES_DIR}" ]]; then
    return 0
  fi
  find "${RELEASES_DIR}" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' 2>/dev/null | sort -r \
    || ls -1 "${RELEASES_DIR}" 2>/dev/null | sort -r
}

cleanup_old_releases() {
  local keep="${RELEASES_TO_KEEP}"
  local releases
  mapfile -t releases < <(list_releases_sorted)

  if [[ "${#releases[@]}" -le "${keep}" ]]; then
    return 0
  fi

  local current
  current="$(basename "$(get_current_release 2>/dev/null || echo '')")"

  local i
  for ((i = keep; i < ${#releases[@]}; i++)); do
    local rel="${releases[$i]}"
    if [[ "${rel}" == "${current}" ]]; then
      continue
    fi
    log_info "Usuwam stary release: ${rel}"
    rm -rf "${RELEASES_DIR}/${rel}"
  done
}

send_monitor_alert() {
  local subject="$1"
  local body="$2"

  if [[ -z "${MONITOR_EMAIL:-}" ]]; then
    return 0
  fi

  if command -v mail >/dev/null 2>&1; then
    echo "${body}" | mail -s "${subject}" "${MONITOR_EMAIL}" 2>/dev/null || true
  elif command -v sendmail >/dev/null 2>&1; then
    {
      echo "To: ${MONITOR_EMAIL}"
      echo "Subject: ${subject}"
      echo
      echo "${body}"
    } | sendmail -t 2>/dev/null || true
  fi
}

verify_vite_manifest() {
  local release_dir="$1"
  if [[ -f "${release_dir}/public/vite-dist/manifest.json" ]]; then
    log_ok "Manifest Vite: public/vite-dist/manifest.json"
    return 0
  fi
  if [[ -f "${release_dir}/public/build/manifest.json" ]]; then
    log_warn "Legacy manifest: public/build/manifest.json (zalecane: vite-dist z CI)"
    return 0
  fi
  log_error "Brak manifestu Vite. Upewnij się, że GitHub Actions zbudował assety przed deployem."
  return 1
}
