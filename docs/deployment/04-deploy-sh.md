# 4. deploy.sh — skrypt deploymentu

Plik: [`deploy/deploy.sh`](../../deploy/deploy.sh)

## Użycie

```bash
./deploy/deploy.sh <staging|production> [opcje]
```

### Opcje

| Opcja | Opis |
|-------|------|
| `--ref SHA\|tag` | Identyfikator wersji (skrócony SHA w nazwie release) |
| `--source-dir PATH` | Katalog z kodem przesłanym z CI (rsync) |
| `--with-migrate` | Uruchom `php artisan migrate --force` |
| `--skip-backup` | Pomiń backup pre-deploy |
| `--maintenance` | Włącz `artisan down` na czas deployu |
| `--skip-composer` | Pomiń `composer install` |

### Przykłady

```bash
# Staging (z GitHub Actions)
./deploy/deploy.sh staging --ref abc1234 --source-dir /var/www/travel-office/incoming/abc1234 --with-migrate

# Produkcja bez migracji
./deploy/deploy.sh production --ref abc1234 --source-dir /var/www/travel-office/incoming/abc1234

# Produkcja z migracją
./deploy/deploy.sh production --ref abc1234 --source-dir /var/www/travel-office/incoming/abc1234 --with-migrate
```

## Sekcje skryptu

### 0/12 Preflight

- Ładuje `deploy/config/app.env`
- Sprawdza użytkownika `deploy` (lub `ALLOW_NON_DEPLOY_USER=1`)
- Weryfikuje PHP, composer, wolne miejsce (min. 2 GB)
- Sprawdza `APP_DEBUG=false` w `shared/.env`

### 0b/12 Backup pre-deploy

- Wywołuje `backup.sh --quick` (MySQL + .env)
- Pomijane z `--skip-backup`

### 1/12 Przygotowanie release

- Tworzy `releases/YYYYMMDD_HHMMSS_<sha>/`
- Kopiuje kod z `--source-dir` przez rsync (bez `.git`, `.env`, `storage`, `node_modules`)

### 2/12 Symlinki shared

```bash
release/.env    -> ../../shared/.env
release/storage -> ../../shared/storage
```

### 3/12 Composer install

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
```

### 4/12 Weryfikacja Vite

- Wymaga `public/vite-dist/manifest.json` (zbudowane w CI)
- Błąd = deploy przerwany

### 5/12 Storage link + legacy

- `php artisan storage:link`
- Kopiowanie legacy paths (`event-templates`, `program_points`, `turysci.jpg`)
- Opcjonalnie `scripts/fix_server_storage.sh`

### 6/12 Czyszczenie cache

```bash
php artisan optimize:clear
```

### 7/12 Migracje

- Tylko z `--with-migrate`
- **Staging:** migracje domyślnie włączone
- **Production:** migracje domyślnie wyłączone

### 8/12 Cache produkcyjny

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

### 9/12 Atomowe przełączenie

```bash
ln -sfn releases/TIMESTAMP_SHA /var/www/travel-office/current
```

### 10/12 Reload usług

- `sudo systemctl reload php8.4-fpm`
- `php artisan queue:restart`
- `php artisan horizon:terminate` (jeśli Horizon zainstalowany)
- `supervisorctl restart travel-office-worker:*`

### 11/12 Health check

- GET `${APP_URL}/up` — oczekiwany HTTP 200
- Monitoring dysku, RAM, kolejki, MySQL

### 12/12 Cleanup

- Usuwa release starsze niż `RELEASES_TO_KEEP` (domyślnie 5)

## Obsługa błędów

- `set -euo pipefail` — każdy błąd przerywa skrypt
- `trap` — przy błędzie **przed** switch: usuwa broken release
- Po switch: **brak auto-rollback** — użyj `rollback.sh`
- Exit code ≠ 0 → GitHub Actions oznacza job jako failed
- Alert e-mail (jeśli `MONITOR_EMAIL` ustawiony)

## Logi

Wszystkie kroki zapisywane do:

```
/var/www/travel-office/shared/logs/deploy.log
```

Format: `YYYY-MM-DD HH:MM:SS [LEVEL] wiadomość`

## Kolory (TTY)

- `[INFO]` — niebieski
- `[OK]` — zielony
- `[WARN]` — żółty
- `[ERROR]` — czerwony
- `[STEP]` — cyan

## Legacy DB (SOR41)

Na pierwszym deployu na legacy bazie:

```bash
php artisan ufg:install   # zamiast pełnego migrate od zera
php artisan app:sync-migration-baseline  # jeśli dotyczy
```

Pełny `migrate` na staging; produkcja — świadomie z `--with-migrate`.
