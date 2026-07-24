#!/usr/bin/env bash
# Zwraca ścieżkę do PHP 8.4 (SOR41: CI/deploy = 8.4).
# Kolejność: PHP_BIN z env → php84 (Remi SCL) → php z PATH (z ostrzeżeniem przy 8.5+).
set -euo pipefail

PHP_BIN="${PHP_BIN:-}"
PHP_BIN="${PHP_BIN//$'\r'/}"

resolve() {
  local bin="$1"
  if [[ -x "$bin" ]] || command -v "$bin" >/dev/null 2>&1; then
    command -v "$bin"
    return 0
  fi
  return 1
}

if [[ -n "${PHP_BIN:-}" ]]; then
  if resolve "$PHP_BIN"; then
    exit 0
  fi
  echo "php-bin: PHP_BIN=$PHP_BIN nie istnieje lub nie jest wykonywalny" >&2
  exit 1
fi

if resolve php84; then
  exit 0
fi

if resolve php; then
  ver="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
  major="$(php -r 'echo PHP_MAJOR_VERSION;')"
  minor="$(php -r 'echo PHP_MINOR_VERSION;')"
  if (( major > 8 || (major == 8 && minor >= 5) )); then
    echo "php-bin: ostrzeżenie — systemowe PHP $ver; projekt wymaga 8.4 (sudo ./scripts/install-php84.sh)" >&2
  fi
  exit 0
fi

echo "php-bin: brak php w PATH" >&2
exit 1
