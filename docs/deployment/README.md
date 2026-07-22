# SOR41 — Dokumentacja deploymentu

Kompletne środowisko wdrożeniowe dla Laravel 12 + Filament 3 na Ubuntu 24.04 (VPS TEST + VPS PROD).

## Spis treści

| Rozdział | Plik | Opis |
|----------|------|------|
| 1 | [01-architektura.md](01-architektura.md) | Przepływ Developer → GitHub → Actions → VPS |
| 2 | [02-struktura-vps.md](02-struktura-vps.md) | Katalogi releases/current/shared |
| 3 | [03-git-workflow.md](03-git-workflow.md) | main, develop, feature, release, hotfix |
| 4 | [04-deploy-sh.md](04-deploy-sh.md) | Skrypt deploy.sh — sekcje i opcje |
| 5 | [05-rollback-sh.md](05-rollback-sh.md) | Rollback release |
| 6 | [06-backup-sh.md](06-backup-sh.md) | Backup OS + integracja app:backup |
| 7 | [07-github-actions.md](07-github-actions.md) | CI/CD, secrets, bezpieczeństwo |
| 8 | [08-cron.md](08-cron.md) | Scheduler, backup, monitoring |
| 9 | [09-monitoring.md](09-monitoring.md) | Health, alerty, logi |
| 10 | [10-bezpieczenstwo.md](10-bezpieczenstwo.md) | SSH, UFW, Fail2Ban, uprawnienia |
| 11 | [11-vps-od-zera.md](11-vps-od-zera.md) | Pełna instalacja od pustego VPS |

## Szybki start (serwer już skonfigurowany)

```bash
# Na VPS — pierwsza konfiguracja
sudo mkdir -p /var/www/travel-office/{releases,shared,backups,incoming}
sudo chown -R deploy:www-data /var/www/travel-office
cp deploy/config/app.env.example deploy/config/app.env
nano deploy/config/app.env   # APP_URL, MONITOR_EMAIL

# shared/.env — jednorazowo
cp .env.example /var/www/travel-office/shared/.env
nano /var/www/travel-office/shared/.env

# Ręczny deploy (gdy CI niedostępne)
./deploy/deploy.sh staging --source-dir /path/to/code --with-migrate
```

## Pliki w repozytorium

```
deploy/
├── deploy.sh
├── rollback.sh
├── backup.sh
├── lib/common.sh
├── lib/healthcheck.sh
└── config/
    ├── app.env.example
    ├── nginx/travel-office.conf
    ├── supervisor/
    ├── fail2ban/jail.local
    ├── monitoring/
    └── sudoers/travel-office-deploy

.github/workflows/
├── verify.yml
├── verify-reusable.yml
├── deploy-staging.yml
└── deploy-production.yml
```

## Legacy

- `scripts/deploy_production.sh` — wrapper kompatybilności (git pull → zalecany model releases)
- `scripts/build_deploy_package.sh` — ręczna paczka ZIP (awaryjnie)
