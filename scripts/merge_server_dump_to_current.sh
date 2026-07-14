#!/usr/bin/env bash
set -euo pipefail

# Merge data from a "server" dump into a "current structure" dump.
#
# This script:
# - creates 2 temporary databases: <base>_src and <base>_dst
# - imports:
#   - src: pliki/host378742_sor26.sql (server data)
#   - dst: pliki/database.sql (good structure; may contain some baseline data)
# - merges missing rows src -> dst using scripts/merge_mysql_databases.php
#
# Usage:
#   ./scripts/merge_server_dump_to_current.sh [base_db_name]
#
# Example:
#   ./scripts/merge_server_dump_to_current.sh host378742_sor26_merge
#
# Connection is taken from .env (DB_HOST/DB_PORT/DB_USERNAME/DB_PASSWORD).

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

BASE="${1:-host378742_sor26_merge}"
SRC_DB="${BASE}_src"
DST_DB="${BASE}_dst"

SRC_DUMP="pliki/host378742_sor26.sql"
DST_DUMP="pliki/database.sql"

if [[ ! -f "$SRC_DUMP" ]]; then
  echo "Missing source dump: $SRC_DUMP" >&2
  exit 2
fi
if [[ ! -f "$DST_DUMP" ]]; then
  echo "Missing destination dump: $DST_DUMP" >&2
  exit 3
fi
if [[ ! -f ".env" ]]; then
  echo "Missing .env" >&2
  exit 4
fi

read_env() {
  local key="$1"
  grep -E "^${key}=" .env 2>/dev/null | head -1 | cut -d= -f2- | tr -d '\r' | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"
}

DB_HOST="$(read_env DB_HOST)"; DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="$(read_env DB_PORT)"; DB_PORT="${DB_PORT:-3306}"
DB_USERNAME="$(read_env DB_USERNAME)"; DB_USERNAME="${DB_USERNAME:-sor}"
DB_PASSWORD="$(read_env DB_PASSWORD)"; DB_PASSWORD="${DB_PASSWORD:-sor_secret}"

mysql_root() {
  if [[ -n "${MYSQL_ROOT_PASSWORD:-}" ]]; then
    mysql -h "$DB_HOST" -P "$DB_PORT" -u root -p"$MYSQL_ROOT_PASSWORD" "$@"
    return
  fi
  if mysql -h "$DB_HOST" -P "$DB_PORT" -u root -e "SELECT 1" >/dev/null 2>&1; then
    mysql -h "$DB_HOST" -P "$DB_PORT" -u root "$@"
    return
  fi
  if sudo mysql -h "$DB_HOST" -P "$DB_PORT" -e "SELECT 1" >/dev/null 2>&1; then
    sudo mysql -h "$DB_HOST" -P "$DB_PORT" "$@"
    return
  fi
  if sudo mysql -e "SELECT 1" >/dev/null 2>&1; then
    sudo mysql "$@"
    return
  fi
  echo "ERROR: cannot connect as MySQL admin (root)." >&2
  echo "Hint: export MYSQL_ROOT_PASSWORD=... or configure sudo mysql access." >&2
  exit 10
}

mysql_app() {
  mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$@"
}

echo "Creating temp databases: $SRC_DB, $DST_DB"
mysql_root -e "DROP DATABASE IF EXISTS \`$SRC_DB\`; CREATE DATABASE \`$SRC_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql_root -e "DROP DATABASE IF EXISTS \`$DST_DB\`; CREATE DATABASE \`$DST_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql_root -e "GRANT ALL PRIVILEGES ON \`$SRC_DB\`.* TO '${DB_USERNAME}'@'localhost'; GRANT ALL PRIVILEGES ON \`$SRC_DB\`.* TO '${DB_USERNAME}'@'127.0.0.1';"
mysql_root -e "GRANT ALL PRIVILEGES ON \`$DST_DB\`.* TO '${DB_USERNAME}'@'localhost'; GRANT ALL PRIVILEGES ON \`$DST_DB\`.* TO '${DB_USERNAME}'@'127.0.0.1'; FLUSH PRIVILEGES;"

echo "Importing src dump (server): $SRC_DUMP"
mysql_app "$SRC_DB" < "$SRC_DUMP"

echo "Importing dst dump (current structure): $DST_DUMP"
mysql_app "$DST_DB" < "$DST_DUMP"

echo "Merging src -> dst (missing rows / common columns)"
php scripts/merge_mysql_databases.php "$SRC_DB" "$DST_DB"

echo ""
echo "Done."
echo "Merged database: $DST_DB"
echo "You can inspect it and (optionally) dump it with mysqldump."
