#!/usr/bin/env bash
# Alert gdy wykorzystanie RAM przekroczy próg.
set -euo pipefail

THRESHOLD="${RAM_ALERT_THRESHOLD:-90}"

if [[ ! -f /proc/meminfo ]]; then
  exit 0
fi

read -r _ total _ < <(grep '^MemTotal:' /proc/meminfo)
read -r _ avail _ < <(grep '^MemAvailable:' /proc/meminfo)

if [[ "${total}" -eq 0 ]]; then
  exit 0
fi

used_pct=$(( (total - avail) * 100 / total ))

if [[ "${used_pct}" -ge "${THRESHOLD}" ]]; then
  echo "RAM ALERT: ${used_pct}% użyte (próg ${THRESHOLD}%)"
  exit 1
fi

exit 0
