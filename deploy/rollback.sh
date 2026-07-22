#!/usr/bin/env bash
# SOR41 — rollback do poprzedniego release.
#
# Użycie:
#   ./deploy/rollback.sh list
#   ./deploy/rollback.sh previous [staging|production]
#   ./deploy/rollback.sh to <release_name|numer> [staging|production]
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/common.sh
source "${SCRIPT_DIR}/lib/common.sh"

ENV_NAME="production"
TARGET=""

usage() {
  cat <<EOF
Użycie:
  rollback.sh list [staging|production]
  rollback.sh previous [staging|production]
  rollback.sh to <release_name|numer> [staging|production]

Przykłady:
  rollback.sh list
  rollback.sh previous staging
  rollback.sh to 20260722_120000_abc1234 production
  rollback.sh to 2 production
EOF
}

parse_env() {
  local arg="${1:-production}"
  case "${arg}" in
    staging|production) ENV_NAME="${arg}" ;;
    *)
      if [[ -d "${RELEASES_DIR}/${arg}" ]]; then
        TARGET="${arg}"
      else
        log_error "Nieznane środowisko lub release: ${arg}"
        exit 1
      fi
      ;;
  esac
}

print_release_list() {
  load_deploy_config "${ENV_NAME}"
  require_app_base

  local current
  current="$(basename "$(get_current_release 2>/dev/null || echo '')")"
  local releases
  mapfile -t releases < <(list_releases_sorted)

  if [[ "${#releases[@]}" -eq 0 ]]; then
    log_warn "Brak releases w ${RELEASES_DIR}"
    exit 0
  fi

  echo ""
  echo -e "${COLOR_BOLD}Release                    Aktywny  Katalog${COLOR_RESET}"
  echo "────────────────────────────────────────────────────────────"

  local i=1
  for rel in "${releases[@]}"; do
    local marker=" "
    if [[ "${rel}" == "${current}" ]]; then
      marker="*"
    fi
    printf " %2d) %-24s [%s]  %s\n" "${i}" "${rel}" "${marker}" "${RELEASES_DIR}/${rel}"
    ((i++)) || true
  done
  echo ""
  echo "* = aktualny current"
  echo ""
}

resolve_target_release() {
  local arg="$1"
  local releases
  mapfile -t releases < <(list_releases_sorted)

  if [[ "${#releases[@]}" -eq 0 ]]; then
    log_error "Brak releases do rollbacku"
    exit 1
  fi

  if [[ "${arg}" == "previous" ]]; then
    local current
    current="$(basename "$(get_current_release 2>/dev/null || echo '')")"
    local found_current=0
    local rel
    for rel in "${releases[@]}"; do
      if [[ "${found_current}" -eq 1 ]]; then
        TARGET="${rel}"
        return 0
      fi
      if [[ "${rel}" == "${current}" ]]; then
        found_current=1
      fi
    done
    log_error "Brak poprzedniego release (jest tylko jeden?)"
    exit 1
  fi

  if [[ "${arg}" =~ ^[0-9]+$ ]]; then
    local idx="${arg}"
    if [[ "${idx}" -lt 1 ]] || [[ "${idx}" -gt "${#releases[@]}" ]]; then
      log_error "Numer spoza zakresu: ${idx} (1-${#releases[@]})"
      exit 1
    fi
    TARGET="${releases[$((idx - 1))]}"
    return 0
  fi

  if [[ -d "${RELEASES_DIR}/${arg}" ]]; then
    TARGET="${arg}"
    return 0
  fi

  log_error "Nie znaleziono release: ${arg}"
  exit 1
}

perform_rollback() {
  local target_dir="${RELEASES_DIR}/${TARGET}"
  local current
  current="$(basename "$(get_current_release 2>/dev/null || echo '')")"

  if [[ "${TARGET}" == "${current}" ]]; then
    log_warn "Release ${TARGET} jest już aktywny"
    exit 0
  fi

  if [[ ! -f "${target_dir}/artisan" ]]; then
    log_error "Release nie wygląda na poprawny Laravel: ${target_dir}"
    exit 1
  fi

  log_step "1/4" "Rollback ${current} → ${TARGET}"
  ln -sfn "${target_dir}" "${CURRENT_LINK}"
  log_ok "current → ${target_dir}"

  log_step "2/4" "Reload usług"
  reload_services "${target_dir}"

  log_step "3/4" "Health check"
  if [[ -x "${SCRIPT_DIR}/lib/healthcheck.sh" ]]; then
    HEALTH_CHECK_URL="${HEALTH_CHECK_URL}" APP_ENV_NAME="${ENV_NAME}" \
      ALLOW_NON_DEPLOY_USER=1 "${SCRIPT_DIR}/lib/healthcheck.sh" || {
      log_error "Health check po rollbacku nie powiódł się"
      exit 1
    }
  fi

  log_step "4/4" "Log rollbacku"
  log_ok "Rollback zakończony: ${TARGET}"
  send_monitor_alert "[SOR41] Rollback OK — ${ENV_NAME}" "Przywrócono release: ${TARGET}\nPoprzedni: ${current}\nURL: ${APP_URL}"
}

main() {
  if [[ $# -lt 1 ]]; then
    usage
    exit 1
  fi

  local cmd="$1"
  shift

  case "${cmd}" in
    list)
      ENV_NAME="${1:-production}"
      parse_env "${ENV_NAME}"
      load_deploy_config "${ENV_NAME}"
      print_release_list
      ;;
    previous)
      ENV_NAME="${1:-production}"
      load_deploy_config "${ENV_NAME}"
      require_app_base
      resolve_target_release "previous"
      perform_rollback
      ;;
    to)
      [[ $# -ge 1 ]] || { log_error "Podaj nazwę release lub numer"; exit 1; }
      local arg="$1"
      shift
      ENV_NAME="${1:-production}"
      load_deploy_config "${ENV_NAME}"
      require_app_base
      resolve_target_release "${arg}"
      perform_rollback
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      log_error "Nieznana komenda: ${cmd}"
      usage
      exit 1
      ;;
  esac
}

main "$@"
