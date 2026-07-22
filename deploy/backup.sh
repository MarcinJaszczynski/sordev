#!/usr/bin/env bash
# SOR41 — backup na poziomie OS (MySQL, storage, .env, nginx, supervisor).
#
# Użycie:
#   ./deploy/backup.sh [--env staging|production]
#   ./deploy/backup.sh --quick [--env staging|production]
#   ./deploy/backup.sh --keep 7
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/common.sh
source "${SCRIPT_DIR}/lib/common.sh"

ENV_NAME="production"
QUICK=0
KEEP_OVERRIDE=""

usage() {
  cat <<EOF
Użycie: backup.sh [opcje]

Opcje:
  --env staging|production   Środowisko (domyślnie: production)
  --quick                    Tylko MySQL + .env (pre-deploy)
  --keep N                   Nadpisz retencję kopii MySQL
  -h, --help                 Pomoc
EOF
}

parse_args() {
  while [[ $# -gt 0 ]]; do
    case "$1" in
      --env)
        ENV_NAME="${2:-}"
        shift 2
        ;;
      --quick) QUICK=1; shift ;;
      --keep)
        KEEP_OVERRIDE="${2:-}"
        shift 2
        ;;
      -h|--help)
        usage
        exit 0
        ;;
      *)
        log_error "Nieznany argument: $1"
        exit 1
        ;;
    esac
  done
}

rotate_backups() {
  local dir="$1"
  local keep="$2"
  local pattern="${3:-*}"

  [[ -d "${dir}" ]] || return 0

  local count
  count="$(find "${dir}" -maxdepth 1 -type f -name "${pattern}" 2>/dev/null | wc -l | tr -d ' ')"

  if [[ "${count}" -le "${keep}" ]]; then
    return 0
  fi

  find "${dir}" -maxdepth 1 -type f -name "${pattern}" -printf '%T@ %p\n' 2>/dev/null \
    | sort -n \
    | head -n "$((count - keep))" \
    | cut -d' ' -f2- \
    | while read -r old; do
        log_info "Rotacja: usuwam ${old}"
        rm -f "${old}"
      done
}

backup_mysql() {
  local ts="$1"
  local db_host db_port db_name db_user db_pass
  local out="${BACKUPS_DIR}/mysql/backup_${ts}.sql.gz"

  db_host="$(read_env_value DB_HOST || echo '127.0.0.1')"
  db_port="$(read_env_value DB_PORT || echo '3306')"
  db_name="$(read_env_value DB_DATABASE || true)"
  db_user="$(read_env_value DB_USERNAME || true)"
  db_pass="$(read_env_value DB_PASSWORD || true)"

  if [[ -z "${db_name}" ]] || [[ -z "${db_user}" ]]; then
    log_error "Brak DB_DATABASE lub DB_USERNAME w shared/.env"
    return 1
  fi

  require_command mysqldump

  log_info "Backup MySQL: ${db_name} @ ${db_host}:${db_port}"

  export MYSQL_PWD="${db_pass}"
  mysqldump \
    --host="${db_host}" \
    --port="${db_port}" \
    --user="${db_user}" \
    --single-transaction \
    --routines \
    --triggers \
    --quick \
    "${db_name}" | gzip -9 > "${out}"
  unset MYSQL_PWD

  log_ok "MySQL → ${out} ($(du -h "${out}" | cut -f1))"
}

backup_env_file() {
  local ts="$1"
  local out="${BACKUPS_DIR}/env/backup_${ts}.env"

  cp -a "${SHARED_DIR}/.env" "${out}"
  chmod 600 "${out}"
  log_ok ".env → ${out}"
}

backup_storage() {
  local ts="$1"
  local out="${BACKUPS_DIR}/storage/backup_${ts}.tar.gz"

  log_info "Backup storage (bez cache)..."

  tar -czf "${out}" \
    -C "${SHARED_DIR}" \
    --exclude='storage/framework/cache' \
    --exclude='storage/framework/views' \
    --exclude='storage/framework/sessions' \
    --exclude='storage/logs/*.log' \
    --exclude='storage/backups' \
    storage 2>/dev/null || {
      log_warn "Częściowy backup storage — sprawdź uprawnienia"
    }

  log_ok "storage → ${out} ($(du -h "${out}" | cut -f1))"
}

backup_nginx_config() {
  local ts="$1"
  local out="${BACKUPS_DIR}/config/nginx/backup_${ts}.tar.gz"

  if [[ -d /etc/nginx/sites-available ]]; then
    tar -czf "${out}" -C /etc/nginx sites-available sites-enabled 2>/dev/null || true
    log_ok "nginx → ${out}"
  else
    log_warn "Brak /etc/nginx — pominięto backup nginx"
  fi
}

backup_supervisor_config() {
  local ts="$1"
  local out="${BACKUPS_DIR}/config/supervisor/backup_${ts}.tar.gz"

  if [[ -d /etc/supervisor/conf.d ]]; then
    tar -czf "${out}" -C /etc/supervisor conf.d supervisord.conf 2>/dev/null || true
    log_ok "supervisor → ${out}"
  else
    log_warn "Brak /etc/supervisor — pominięto backup supervisor"
  fi
}

main() {
  parse_args "$@"
  load_deploy_config "${ENV_NAME}"
  require_app_base

  DEPLOY_LOG_FILE="${BACKUP_LOG_FILE}"

  local ts
  ts="$(date '+%Y%m%d_%H%M%S')"

  log_info "=== Backup start (${ENV_NAME}, quick=${QUICK}) ==="

  backup_mysql "${ts}" || exit 1
  backup_env_file "${ts}"

  if [[ "${QUICK}" -eq 0 ]]; then
    backup_storage "${ts}"
    backup_nginx_config "${ts}"
    backup_supervisor_config "${ts}"
  fi

  local keep_mysql="${KEEP_OVERRIDE:-${BACKUP_KEEP_MYSQL}}"
  rotate_backups "${BACKUPS_DIR}/mysql" "${keep_mysql}" "backup_*.sql.gz"
  rotate_backups "${BACKUPS_DIR}/env" "${BACKUP_KEEP_ENV}" "backup_*.env"
  rotate_backups "${BACKUPS_DIR}/storage" "${BACKUP_KEEP_STORAGE}" "backup_*.tar.gz"
  rotate_backups "${BACKUPS_DIR}/config/nginx" "${BACKUP_KEEP_CONFIG}" "backup_*.tar.gz"
  rotate_backups "${BACKUPS_DIR}/config/supervisor" "${BACKUP_KEEP_CONFIG}" "backup_*.tar.gz"

  log_ok "Backup zakończony: ${ts}"
  exit 0
}

main "$@"
