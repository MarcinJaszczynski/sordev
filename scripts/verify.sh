#!/usr/bin/env bash

set -euo pipefail

RUN_TESTS="${VERIFY_RUN_TESTS:-0}"

if [[ ! -f artisan ]]; then
  echo "Uruchom skrypt z katalogu glownego projektu (tam gdzie jest artisan)."
  exit 1
fi

echo "[1/8] Composer validate"
composer validate --no-check-publish --strict

echo "[2/8] Czyszczenie cache"
php artisan optimize:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo "[3/8] Lint PHP (app, config, routes, database, tests)"
find app config routes database tests -type f -name "*.php" -print0 | xargs -0 -n1 php -l >/tmp/php_lint.out
cat /tmp/php_lint.out | tail -n 20

echo "[4/8] Sprawdzenie tras i kontenera"
php artisan route:list >/dev/null
php artisan about >/dev/null

echo "[5/8] Kompilacja widokow Blade"
php artisan view:cache

echo "[6/8] Odtworzenie cache runtime"
php artisan optimize:clear

if [[ -x vendor/bin/pint ]]; then
  echo "[7/8] Pint (test mode)"
  vendor/bin/pint --test
else
  echo "[7/8] Pint pomiety (brak vendor/bin/pint)"
fi

if [[ "$RUN_TESTS" == "1" ]]; then
  echo "[8/8] Testy"
  php artisan test --stop-on-failure
else
  echo "[8/8] Testy pomiete (ustaw VERIFY_RUN_TESTS=1 aby wlaczyc)"
fi

echo "Verify OK"
