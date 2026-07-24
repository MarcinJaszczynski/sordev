#!/usr/bin/env bash
# Uruchamia composer z PHP wybranym przez scripts/php-bin.sh.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

PHP="$("$ROOT/scripts/php-bin.sh")"
COMPOSER="$(command -v composer)"

ver="$("$PHP" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
major="$("$PHP" -r 'echo PHP_MAJOR_VERSION;')"
minor="$("$PHP" -r 'echo PHP_MINOR_VERSION;')"

extra=()
if (( major > 8 || (major == 8 && minor >= 5) )); then
  extra+=(--ignore-platform-req=php)
fi

exec "$PHP" "$COMPOSER" "${extra[@]}" "$@"
