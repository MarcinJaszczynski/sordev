# 6. backup.sh — kopie zapasowe

Plik: [`deploy/backup.sh`](../../deploy/backup.sh)

## Dwa poziomy backupu

| Poziom | Narzędzie | Co backupuje | Gdzie |
|--------|-----------|--------------|-------|
| Aplikacja | `php artisan app:backup` | db, storage, code, env (ZIP) | `shared/storage/backups/` |
| OS | `deploy/backup.sh` | MySQL dump, storage tar, .env, nginx, supervisor | `/var/www/travel-office/backups/` |

Oba poziomy się uzupełniają. Scheduler Laravel (`app:backup` o 02:00) działa niezależnie.

## Użycie

```bash
./deploy/backup.sh [--env staging|production]
./deploy/backup.sh --quick [--env staging|production]
./deploy/backup.sh --keep 7
```

## Komponenty

### MySQL

```bash
mysqldump --single-transaction --routines --triggers | gzip
→ backups/mysql/backup_YYYYMMDD_HHMMSS.sql.gz
```

Dane z `shared/.env`: `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.

### Storage

```bash
tar -czf backups/storage/backup_TIMESTAMP.tar.gz shared/storage
```

Wykluczenia: `framework/cache`, `framework/views`, `framework/sessions`, duże logi, `storage/backups`.

### .env

```bash
cp shared/.env backups/env/backup_TIMESTAMP.env
chmod 600
```

### Nginx

```bash
tar -czf backups/config/nginx/backup_TIMESTAMP.tar.gz /etc/nginx/sites-available /etc/nginx/sites-enabled
```

### Supervisor

```bash
tar -czf backups/config/supervisor/backup_TIMESTAMP.tar.gz /etc/supervisor/conf.d
```

## Tryb --quick (pre-deploy)

Tylko MySQL + .env — szybki backup przed każdym deployem (domyślnie w `deploy.sh`).

## Rotacja

| Typ | Domyślna retencja | Zmienna |
|-----|-------------------|---------|
| MySQL | 14 kopii | `BACKUP_KEEP_MYSQL` |
| storage | 7 kopii | `BACKUP_KEEP_STORAGE` |
| .env | 30 kopii | `BACKUP_KEEP_ENV` |
| nginx/supervisor | 7 kopii | `BACKUP_KEEP_CONFIG` |

Nadpisanie: `--keep N` (dotyczy MySQL).

## Przywracanie MySQL

```bash
gunzip -c backups/mysql/backup_20260722_010015.sql.gz | mysql -h127.0.0.1 -uUSER -p DATABASE
```

## Przywracanie storage

```bash
tar -xzf backups/storage/backup_20260722_010015.tar.gz -C /var/www/travel-office/shared/
```

## Przywracanie przez panel

Filament → Backup Manager → `app:restore` (z kopii aplikacyjnych w `storage/backups/`).

## Logi

```
/var/www/travel-office/shared/logs/backup.log
```

## Cron (patrz rozdział 8)

```cron
15 1 * * * deploy /var/www/travel-office/deploy/backup.sh >> /var/www/travel-office/shared/logs/backup.log 2>&1
```
