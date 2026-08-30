# Kopie zapasowe i zrzuty bazy (SOR41)

Jedno miejsce: lokalny snapshot MySQL + jak działa `app:backup` / `app:restore` na macOS (Herd + DBngin).

## Snapshot w tym katalogu

| Plik | Baza | Utworzono | Uwagi |
|------|------|-----------|--------|
| `sor41_snapshot_20260830.sql.gz` | `sor41` @ `127.0.0.1:3306` | 2026-08-30 | mysqldump + gzip (~10 MB); single-transaction, routines, triggers |

**Uwaga:** plik może zawierać dane operacyjne / osobowe. Nie wrzucaj go na publiczny remote bez świadomej decyzji. Surowy `*.sql` jest w `.gitignore`; ten katalog trzyma skompresowany `.sql.gz`.

### Przywrócenie tego snapshotu

```bash
# z katalogu projektu; klient MySQL z DBngin (Herd)
gunzip -c database/dumps/sor41_snapshot_20260830.sql.gz \
  | /Users/Shared/DBngin/mysql/8.4.7_arm64/bin/mysql \
      --host=127.0.0.1 --port=3306 --user=root sor41
```

Jeśli `mysql` jest już w `PATH`:

```bash
gunzip -c database/dumps/sor41_snapshot_20260830.sql.gz | mysql -h 127.0.0.1 -P 3306 -u root sor41
```

Przed importem warto zrobić świeży backup (`php artisan app:backup --components=db`).

### Nowy zrzut (ręcznie)

```bash
mkdir -p database/dumps
/Users/Shared/DBngin/mysql/8.4.7_arm64/bin/mysqldump \
  --host=127.0.0.1 --port=3306 --user=root \
  --single-transaction --routines --triggers --quick sor41 \
  | gzip -c > "database/dumps/sor41_snapshot_$(date +%Y%m%d).sql.gz"
```

Albo przez aplikację (ZIP w `storage/backups/`, katalog gitignored):

```bash
php artisan app:backup --components=db
# albo pełniej: php artisan app:backup --full
```

---

## Backup aplikacji (`app:backup` / panel Filament)

- Komenda: `php artisan app:backup` — komponenty: `db`, `storage`, `code`, `env` (lub `--full`).
- Przywracanie: `php artisan app:restore {ścieżka.zip}`.
- UI: panel admin → **Backup Manager**.
- Archiwa ZIP: `storage/backups/` (poza gitem).
- Harmonogram: `config/backup.php` + scheduler w `bootstrap/app.php`.

### MySQL na Herd / DBngin

PHP-FPM często **nie ma** `mysqldump` / `mysql` w `PATH`. Aplikacja szuka binarek przez `App\Support\DatabaseCliBinary`:

1. `which`
2. ścieżki systemowe + Homebrew
3. **DBngin**: `/Users/Shared/DBngin/mysql/*/bin/{mysqldump|mysql}` (najnowsza wersja pierwsza)

Przy błędzie komponentu (np. brak dumpa) ZIP jest **usuwany** — nie zostaje pusta „kopia” 0 MB.

### Deploy (serwer)

OS-level: `deploy/backup.sh` (osobny mechanizm od ZIP-ów aplikacji).
