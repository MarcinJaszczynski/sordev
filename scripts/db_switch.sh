#!/usr/bin/env bash
set -euo pipefail

# Helper to run Laravel commands against sqlite or mysql without editing app files.
# Usage examples:
#   ./scripts/db_switch.sh status
#   ./scripts/db_switch.sh sqlite "php artisan migrate:status"
#   ./scripts/db_switch.sh mysql "php artisan migrate:status"
#
# Environment for mysql mode can be overridden:
#   MYSQL_DB=host378742_sor26 MYSQL_USER=sor MYSQL_PASS=sor_secret ./scripts/db_switch.sh mysql "php artisan tinker"

MODE="${1:-status}"
CMD="${2:-}"

SQLITE_DB_PATH="${SQLITE_DB_PATH:-database/database.sqlite}"
MYSQL_HOST="${MYSQL_HOST:-127.0.0.1}"
MYSQL_PORT="${MYSQL_PORT:-3306}"
MYSQL_DB="${MYSQL_DB:-host378742_sor26}"
MYSQL_USER="${MYSQL_USER:-sor}"
MYSQL_PASS="${MYSQL_PASS:-sor_secret}"

if [[ "$MODE" == "status" ]]; then
  echo "Available modes: sqlite, mysql"
  echo "sqlite: DB_CONNECTION=sqlite DB_DATABASE=${SQLITE_DB_PATH}"
  echo "mysql: DB_CONNECTION=mysql DB_HOST=${MYSQL_HOST} DB_PORT=${MYSQL_PORT} DB_DATABASE=${MYSQL_DB} DB_USERNAME=${MYSQL_USER}"
  exit 0
fi

if [[ -z "$CMD" ]]; then
  echo "Provide command as second argument, e.g.:"
  echo "  ./scripts/db_switch.sh mysql \"php artisan migrate:status\""
  exit 1
fi

if [[ "$MODE" == "sqlite" ]]; then
  DB_CONNECTION=sqlite \
  DB_DATABASE="$SQLITE_DB_PATH" \
  bash -lc "$CMD"
  exit $?
fi

if [[ "$MODE" == "mysql" ]]; then
  DB_CONNECTION=mysql \
  DB_HOST="$MYSQL_HOST" \
  DB_PORT="$MYSQL_PORT" \
  DB_DATABASE="$MYSQL_DB" \
  DB_USERNAME="$MYSQL_USER" \
  DB_PASSWORD="$MYSQL_PASS" \
  bash -lc "$CMD"
  exit $?
fi

echo "Unknown mode: $MODE"
exit 2
