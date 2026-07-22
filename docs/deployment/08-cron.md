# 8. Cron — harmonogram zadań

Edytuj crontab użytkownika `deploy`:

```bash
crontab -e -u deploy
```

## Wpisy

```cron
# Laravel Scheduler — JEDYNY wpis wymagany przez framework
* * * * * cd /var/www/travel-office/current && /usr/bin/php artisan schedule:run >> /dev/null 2>&1

# Backup OS-level (uzupełnia app:backup z schedulera Laravel o 02:00)
15 1 * * * /var/www/travel-office/deploy/backup.sh --env production >> /var/www/travel-office/shared/logs/backup.log 2>&1

# Czyszczenie starych logów deploy/monitoring (>30 dni)
0 4 * * 0 find /var/www/travel-office/shared/logs -name "*.log" -mtime +30 -delete

# Monitoring co 5 minut
*/5 * * * * /var/www/travel-office/deploy/lib/healthcheck.sh >> /var/www/travel-office/shared/logs/monitoring/health.log 2>&1
```

## Co robi Laravel Scheduler

Zdefiniowane w [`bootstrap/app.php`](../../bootstrap/app.php):

| Zadanie | Harmonogram |
|---------|-------------|
| `app:backup` | cron z `config/backup.php` (domyślnie 02:00) |
| `app:backup-prune` | j.w. |
| `app:notify-margin-discrepancies` | daily 07:00 |
| `tfg:notify-monthly-reminder` | daily (do 14. dnia miesiąca) |
| `BuildMonthlyTfgFeedJob` | monthly |
| `tfg:notify-correction-deadlines` | daily 08:00 |

Wymaga **dokładnie jednego** wpisu cron `* * * * * schedule:run`.

## Logrotate (systemowy)

Utwórz `/etc/logrotate.d/travel-office`:

```
/var/www/travel-office/shared/logs/*.log
/var/www/travel-office/shared/storage/logs/*.log
/var/log/nginx/travel-office-*.log
{
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 deploy www-data
    sharedscripts
    postrotate
        systemctl reload nginx > /dev/null 2>&1 || true
    endscript
}
```

## Dlaczego cron NIE powinien wykonywać deploymentu

| Problem | Opis |
|---------|------|
| Brak CI | Deploy bez testów i build Vite |
| Brak atomowości | `git pull` w cron = stan pośredni |
| Brak audytu | Trudno powiązać deploy z commitem/PR |
| Konflikty | Równoległy cron + ręczny deploy |
| Brak artefaktów | Prod bez Node — brak manifestu Vite |
| Brak approval | Prod deploy bez review |
| Rollback | Cron nie tworzy releases |

**Deploy wyłącznie przez GitHub Actions + `deploy.sh`.**

## Supervisor vs cron dla schedulera

**Zalecane:** cron + `schedule:run` (standard Laravel).

Supervisor `schedule:work` ([`travel-office-scheduler.conf`](../../deploy/config/supervisor/travel-office-scheduler.conf)) — tylko fallback gdy cron niedostępny.

## Weryfikacja crona

```bash
grep CRON /var/log/syslog | tail -5
cd /var/www/travel-office/current && php artisan schedule:list
```
