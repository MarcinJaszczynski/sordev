# 9. Monitoring

## Logi

| Log | Ścieżka |
|-----|---------|
| Laravel | `shared/storage/logs/laravel.log` |
| Deploy | `shared/logs/deploy.log` |
| Backup | `shared/logs/backup.log` |
| Health | `shared/logs/monitoring/health.log` |
| Supervisor workers | `shared/logs/supervisor-worker.log` |
| Nginx access | `/var/log/nginx/travel-office-access.log` |
| Nginx error | `/var/log/nginx/travel-office-error.log` |

### Podgląd na żywo

```bash
tail -f /var/www/travel-office/shared/logs/deploy.log
tail -f /var/www/travel-office/shared/storage/logs/laravel.log
```

## Health check

Endpoint Laravel: **`GET /up`** (zdefiniowany w `bootstrap/app.php`).

Skrypt: [`deploy/lib/healthcheck.sh`](../../deploy/lib/healthcheck.sh)

Uruchamiany:
- Po każdym deploy/rollback
- Z crona co 5 minut

```bash
curl -sf https://app.example.com/up
# oczekiwany HTTP 200
```

Opcjonalnie sprawdza stronę logowania admina (HTTP 200).

## Monitoring dysku

[`deploy/config/monitoring/check-disk.sh`](../../deploy/config/monitoring/check-disk.sh)

- Domyślny próg: **85%**
- Zmienna: `DISK_ALERT_THRESHOLD`
- Mount: `DISK_ALERT_MOUNT` (domyślnie `/`)

## Monitoring RAM

[`deploy/config/monitoring/check-ram.sh`](../../deploy/config/monitoring/check-ram.sh)

- Próg: **90%** (`RAM_ALERT_THRESHOLD`)
- Odczyt z `/proc/meminfo`

## Monitoring kolejek

[`deploy/config/monitoring/check-queue.sh`](../../deploy/config/monitoring/check-queue.sh)

- Liczba `failed_jobs` (próg domyślnie 10)
- Redis `LLEN` dla kolejki (próg 500) gdy `QUEUE_CONNECTION=redis`

```bash
cd /var/www/travel-office/current
php artisan queue:failed
php artisan queue:monitor redis:default --max=100
```

## Monitoring bazy

[`deploy/config/monitoring/check-mysql.sh`](../../deploy/config/monitoring/check-mysql.sh)

- `mysqladmin ping` na podstawie `shared/.env`

## Powiadomienia e-mail

Ustaw w `deploy/config/app.env`:

```
MONITOR_EMAIL="ops@example.com"
```

Skrypt wysyła alert przez `mail` lub `sendmail`.

### Konfiguracja msmtp (VPS)

```bash
sudo apt install msmtp msmtp-mta
```

`/etc/msmtprc`:

```
defaults
auth           on
tls            on
tls_trust_file /etc/ssl/certs/ca-certificates.crt
logfile        /var/log/msmtp.log

account        default
host           smtp.postmarkapp.com
port           587
from           monitoring@app.example.com
user           POSTMARK_API_TOKEN
password       POSTMARK_API_TOKEN
```

Alternatywa: aplikacja Laravel wysyła maile przez Postmark (`symfony/postmark-mailer`) — monitoring może używać tego samego SMTP.

## Zewnętrzne monitoringi (opcjonalnie)

| Usługa | Konfiguracja |
|--------|--------------|
| Uptime Kuma | Monitor HTTP → `https://app.example.com/up` |
| Healthchecks.io | Ping co 5 min |
| Datadog / Sentry | APM + błędy Laravel |

## Komendy diagnostyczne

```bash
# Status usług
systemctl status nginx php8.4-fpm redis-server mysql
supervisorctl status

# Kolejka
php artisan queue:work --once
php artisan horizon:status   # jeśli Horizon

# Redis
redis-cli ping
redis-cli LLEN "$(grep REDIS_PREFIX shared/.env | cut -d= -f2)queues:default"

# Dysk
df -h /
du -sh /var/www/travel-office/releases/*
```
