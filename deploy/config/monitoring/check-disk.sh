#!/usr/bin/env bash
# Alert gdy wykorzystanie dysku przekroczy próg.
set -euo pipefail

THRESHOLD="${DISK_ALERT_THRESHOLD:-85}"
MOUNT="${DISK_ALERT_MOUNT:-/}"

used="$(df -P "${MOUNT}" | awk 'NR==2 {gsub(/%/,"",$5); print $5}')"

if [[ "${used}" -ge "${THRESHOLD}" ]]; then
  echo "DISK ALERT: ${MOUNT} w ${used}% (próg ${THRESHOLD}%)"
  exit 1
fi

exit 0
