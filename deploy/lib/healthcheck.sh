#!/usr/bin/env bash
# Health check + monitoring podstawowy — uruchamiany z crona co 5 min.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=common.sh
source "${SCRIPT_DIR}/common.sh"

load_deploy_config "${APP_ENV_NAME:-production}"
require_app_base

ALERTS=()
STATUS=0

add_alert() {
  ALERTS+=("$1")
  STATUS=1
}

check_http_health() {
  local url="${HEALTH_CHECK_URL:-${APP_URL}/up}"
  local code

  if ! command -v curl >/dev/null 2>&1; then
    add_alert "curl niedostępny — pominięto HTTP health check"
    return
  fi

  code="$(curl -sf -o /dev/null -w '%{http_code}' --max-time "${HEALTH_CHECK_TIMEOUT:-30}" "${url}" 2>/dev/null || echo "000")"

  if [[ "${code}" == "200" ]]; then
    log_ok "HTTP ${url} → ${code}"
  else
    add_alert "HTTP health check FAILED: ${url} → ${code}"
  fi
}

check_disk() {
  if [[ -x "${DEPLOY_ROOT}/config/monitoring/check-disk.sh" ]]; then
    "${DEPLOY_ROOT}/config/monitoring/check-disk.sh" || add_alert "Alert dysku — patrz check-disk.sh"
  fi
}

check_ram() {
  if [[ -x "${DEPLOY_ROOT}/config/monitoring/check-ram.sh" ]]; then
    "${DEPLOY_ROOT}/config/monitoring/check-ram.sh" || add_alert "Alert RAM — patrz check-ram.sh"
  fi
}

check_queue() {
  if [[ -x "${DEPLOY_ROOT}/config/monitoring/check-queue.sh" ]] && [[ -L "${CURRENT_LINK}" ]]; then
    "${DEPLOY_ROOT}/config/monitoring/check-queue.sh" "${CURRENT_LINK}" || add_alert "Alert kolejki — patrz check-queue.sh"
  fi
}

check_mysql() {
  if [[ -x "${DEPLOY_ROOT}/config/monitoring/check-mysql.sh" ]]; then
    "${DEPLOY_ROOT}/config/monitoring/check-mysql.sh" || add_alert "Alert MySQL — patrz check-mysql.sh"
  fi
}

main() {
  log_info "=== Health check $(date '+%Y-%m-%d %H:%M:%S') ==="

  check_http_health
  check_disk
  check_ram
  check_queue
  check_mysql

  if [[ "${#ALERTS[@]}" -gt 0 ]]; then
    local body
    body="$(printf '%s\n' "${ALERTS[@]}")"
    log_error "Wykryto ${#ALERTS[@]} alert(ów)"
    send_monitor_alert "[SOR41] Health alert — ${APP_ENV_NAME:-production}" "${body}"
    exit 1
  fi

  log_ok "Wszystkie kontrole OK"
  exit 0
}

main "$@"
