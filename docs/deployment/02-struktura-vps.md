# 2. Struktura katalogów VPS

## Docelowa struktura

```text
/var/www/travel-office/              # APP_BASE
├── current -> releases/20260722_143022_abc1234/
├── releases/
│   ├── 20260722_143022_abc1234/      # pełna kopia kodu + vendor
│   └── 20260722_120000_def5678/
├── shared/
│   ├── .env                          # konfiguracja — NIGDY w release
│   ├── storage/                      # uploady, logi, backups, framework
│   └── logs/
│       ├── deploy.log
│       ├── backup.log
│       └── monitoring/
├── incoming/                         # tymczasowy upload z CI (rsync)
│   └── <GITHUB_SHA>/
├── deploy/                           # skrypty (rsync z repo)
│   ├── deploy.sh
│   ├── rollback.sh
│   └── backup.sh
└── backups/                          # kopie OS-level
    ├── mysql/
    ├── storage/
    ├── env/
    └── config/
        ├── nginx/
        └── supervisor/
```

**Nginx DocumentRoot:** `/var/www/travel-office/current/public`

## Symlinki wewnątrz release

Każdy katalog w `releases/` zawiera:

```text
releases/TIMESTAMP_SHA/
├── .env -> ../../shared/.env
├── storage -> ../../shared/storage
├── artisan
├── vendor/
├── public/
│   └── vite-dist/    # zbudowane w CI
└── ...
```

## Dlaczego to lepsze niż `git pull`

| Aspekt | `git pull` w miejscu | Model releases |
|--------|---------------------|----------------|
| Atomowość | Pliki aktualizowane stopniowo — ryzyko błędów pośrednich | `ln -sfn` — jedna operacja |
| Downtime | Często wymaga `artisan down` | Zero-downtime (reload FPM) |
| Rollback | `git revert` + composer + cache | Symlink wstecz w sekundy |
| Izolacja | Jeden katalog — błąd psuje prod | Stary release nietknięty |
| Audyt | `git log` | Katalogi `timestamp-sha` |
| Dane persistent | Ryzyko nadpisania .env/storage | `shared/` oddzielone |
| Równoległe deploye | Konflikt | Jeden release na raz (concurrency w Actions) |

## Uprawnienia

```bash
# Właściciel deploy, grupa www-data (PHP-FPM)
sudo chown -R deploy:www-data /var/www/travel-office
sudo chmod -R ug+rwx /var/www/travel-office/shared/storage
sudo chmod -R ug+rwx /var/www/travel-office/shared/bootstrap/cache  # jeśli istnieje w shared
```

W każdym release:

```bash
chmod -R ug+rwx storage bootstrap/cache   # przez symlink → shared/storage
```

## Pierwsze utworzenie struktury

```bash
sudo mkdir -p /var/www/travel-office/{releases,shared,backups/{mysql,storage,env,config/{nginx,supervisor}},incoming,shared/logs/monitoring}
sudo chown -R deploy:www-data /var/www/travel-office
```

## Konfiguracja deploy

```bash
cp deploy/config/app.env.example deploy/config/app.env
nano deploy/config/app.env
```

Kluczowe zmienne: `APP_BASE`, `APP_URL`, `DEPLOY_USER`, `PHP_FPM_SERVICE`, `MONITOR_EMAIL`.
