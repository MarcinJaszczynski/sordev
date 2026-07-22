#!/usr/bin/env bash
# Sprawdza failed_jobs i (opcjonalnie) długość kolejki Redis.
set -euo pipefail

APP_ROOT="${1:-/var/www/travel-office/current}"
FAILED_THRESHOLD="${QUEUE_FAILED_THRESHOLD:-10}"

if [[ ! -f "${APP_ROOT}/artisan" ]]; then
  echo "QUEUE: brak artisan w ${APP_ROOT}"
  exit 1
fi

cd "${APP_ROOT}"

failed_count=0
if php artisan tinker --execute="echo \\DB::table('failed_jobs')->count();" 2>/dev/null | grep -Eq '^[0-9]+$'; then
  failed_count="$(php artisan tinker --execute="echo \\DB::table('failed_jobs')->count();" 2>/dev/null | tail -1)"
fi

if [[ "${failed_count}" -gt "${FAILED_THRESHOLD}" ]]; then
  echo "QUEUE ALERT: failed_jobs=${failed_count} (próg ${FAILED_THRESHOLD})"
  exit 1
fi

queue_conn="$(grep -E '^QUEUE_CONNECTION=' "${APP_ROOT}/../shared/.env" 2>/dev/null | cut -d= -f2 | tr -d '\r' || echo '')"
queue_conn="${queue_conn:-$(grep -E '^QUEUE_CONNECTION=' "${APP_ROOT}/.env" 2>/dev/null | cut -d= -f2 | tr -d '\r' || echo '')}"

if [[ "${queue_conn}" == "redis" ]] && command -v redis-cli >/dev/null 2>&1; then
  redis_prefix="$(grep -E '^REDIS_PREFIX=' "${APP_ROOT}/../shared/.env" 2>/dev/null | cut -d= -f2 | tr -d '\r' || echo '')"
  queue_name="$(grep -E '^REDIS_QUEUE=' "${APP_ROOT}/../shared/.env" 2>/dev/null | cut -d= -f2 | tr -d '\r' || echo 'default')"
  queue_key="${redis_prefix}queues:${queue_name}"
  qlen="$(redis-cli LLEN "${queue_key}" 2>/dev/null || echo 0)"
  queue_len_threshold="${QUEUE_LENGTH_THRESHOLD:-500}"

  if [[ "${qlen}" -gt "${queue_len_threshold}" ]]; then
    echo "QUEUE ALERT: Redis LLEN ${queue_key}=${qlen} (próg ${queue_len_threshold})"
    exit 1
  fi
fi

exit 0
