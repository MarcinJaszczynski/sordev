#!/usr/bin/env bash
# Ping MySQL na podstawie shared/.env
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEPLOY_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

# shellcheck source=../../lib/common.sh
source "${DEPLOY_ROOT}/lib/common.sh"

load_deploy_config "${APP_ENV_NAME:-production}"

db_host="$(read_env_value DB_HOST || echo '127.0.0.1')"
db_port="$(read_env_value DB_PORT || echo '3306')"
db_name="$(read_env_value DB_DATABASE || true)"
db_user="$(read_env_value DB_USERNAME || true)"
db_pass="$(read_env_value DB_PASSWORD || true)"

if [[ -z "${db_name}" ]] || [[ -z "${db_user}" ]]; then
  echo "MYSQL: brak konfiguracji DB w shared/.env"
  exit 1
fi

if ! command -v mysqladmin >/dev/null 2>&1; then
  exit 0
fi

export MYSQL_PWD="${db_pass}"
if mysqladmin ping -h "${db_host}" -P "${db_port}" -u "${db_user}" --silent 2>/dev/null; then
  unset MYSQL_PWD
  exit 0
fi
unset MYSQL_PWD

echo "MYSQL ALERT: mysqladmin ping failed (${db_host}:${db_port}/${db_name})"
exit 1
