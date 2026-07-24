#!/usr/bin/env bash
# Instalacja PHP 8.4 obok systemowego PHP (Remi SCL) — Fedora/Nobara 44.
# Nie zastępuje domyślnego php w systemie; dostępne jako php84.
set -euo pipefail

if [[ "${EUID:-$(id -u)}" -ne 0 ]]; then
  echo "Uruchom z sudo: sudo $0"
  exit 1
fi

FEDORA_VER="$(rpm -E '%fedora' 2>/dev/null || echo 44)"
REMIREPO="https://rpms.remirepo.net/fedora/remi-release-${FEDORA_VER}.rpm"

echo "==> Remi repository (Fedora ${FEDORA_VER})"
dnf install -y "$REMIREPO"

echo "==> PHP 8.4 (Software Collection, równolegle z systemowym PHP)"
dnf install -y \
  php84 \
  php84-php-cli \
  php84-php-fpm \
  php84-php-mysqlnd \
  php84-php-mbstring \
  php84-php-xml \
  php84-php-gd \
  php84-php-intl \
  php84-php-pecl-zip \
  php84-php-bcmath \
  php84-php-process \
  php84-php-opcache

echo ""
echo "==> Weryfikacja"
php84 -v | head -1
php84 -m | rg -i '^(pdo_mysql|mbstring|xml|gd|intl|zip|bcmath)$' || true

echo ""
echo "Gotowe. W katalogu projektu:"
echo "  ./scripts/php-bin.sh -v"
echo "  composer install   # bez --ignore-platform-req"
echo "  make dev"
