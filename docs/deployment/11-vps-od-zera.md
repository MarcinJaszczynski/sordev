# 11. VPS od zera — instrukcja krok po kroku

Ubuntu 24.04 LTS, użytkownik z sudo. Powtórz na **VPS TEST** (staging) i **VPS PROD** (produkcja) z odpowiednimi domenami.

Zmienne przykładowe:
- `APP_DOMAIN=app.example.com` (prod) lub `staging.example.com` (test)
- `APP_BASE=/var/www/travel-office`

---

## Krok 1: Połączenie z VPS

```bash
ssh root@203.0.113.10
```

Pierwsze logowanie hasłem z panelu hostingu. Od razu przejdź do konfiguracji kluczy SSH.

---

## Krok 2: Aktualizacja systemu

```bash
apt update && apt upgrade -y
apt install -y curl wget git unzip software-properties-common ufw fail2ban acl
```

---

## Krok 3: Użytkownik deploy

```bash
adduser --disabled-password --gecos "" deploy
usermod -aG sudo deploy
```

Skopiuj klucz SSH (z lokalnej maszyny):

```bash
# Na lokalnym komputerze:
ssh-copy-id deploy@203.0.113.10
```

Wyloguj się i zaloguj jako deploy:

```bash
exit
ssh deploy@203.0.113.10
```

---

## Krok 4: Wyłączenie root i haseł SSH

```bash
sudo nano /etc/ssh/sshd_config
```

Ustaw:

```
PermitRootLogin no
PasswordAuthentication no
PubkeyAuthentication yes
AllowUsers deploy
```

```bash
sudo systemctl restart sshd
```

**Uwaga:** przed restartem upewnij się, że logowanie kluczem jako `deploy` działa w osobnej sesji.

---

## Krok 5: Firewall UFW

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
sudo ufw status
```

---

## Krok 6: Fail2Ban

```bash
sudo apt install -y fail2ban
# Po sklonowaniu repo:
sudo cp /var/www/travel-office/deploy/config/fail2ban/jail.local /etc/fail2ban/jail.local
sudo systemctl enable fail2ban
sudo systemctl restart fail2ban
```

(Tymczasowo skopiuj jail.local ręcznie lub z repozytorium po kroku 15.)

---

## Krok 7: PHP 8.4 + rozszerzenia

```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring php8.4-xml \
  php8.4-curl php8.4-zip php8.4-gd php8.4-intl php8.4-bcmath php8.4-redis php8.4-fileinfo
php -v
```

---

## Krok 8: Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

---

## Krok 9: MySQL 8

```bash
sudo apt install -y mysql-server
sudo mysql_secure_installation
```

Utwórz bazę i użytkownika:

```bash
sudo mysql
```

```sql
CREATE DATABASE sor41 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'sor41'@'localhost' IDENTIFIED BY 'SILNE_HASLO_TUTAJ';
GRANT ALL PRIVILEGES ON sor41.* TO 'sor41'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Import dumpu (legacy, jednorazowo):

```bash
mysql -u sor41 -p sor41 < /path/to/dump.sql
```

---

## Krok 10: Redis

```bash
sudo apt install -y redis-server
sudo systemctl enable redis-server
redis-cli ping
# PONG
```

---

## Krok 11: Nginx

```bash
sudo apt install -y nginx
sudo systemctl enable nginx
```

Struktura katalogów aplikacji:

```bash
sudo mkdir -p /var/www/travel-office/{releases,shared,backups/{mysql,storage,env,config/{nginx,supervisor}},incoming,shared/logs/monitoring}
sudo chown -R deploy:www-data /var/www/travel-office
```

---

## Krok 12: Konfiguracja .env

```bash
cd /var/www/travel-office
# Tymczasowo sklonuj repo aby skopiować .env.example:
git clone git@github.com:ORG/sor41.git /tmp/sor41-setup
cp /tmp/sor41-setup/.env.example /var/www/travel-office/shared/.env
nano /var/www/travel-office/shared/.env
chmod 600 /var/www/travel-office/shared/.env
```

Ustaw m.in.:

```
APP_NAME="SOR41"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.example.com
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sor41
DB_USERNAME=sor41
DB_PASSWORD=SILNE_HASLO_TUTAJ
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
LOG_LEVEL=error
DEBUGBAR_ENABLED=false
```

Wygeneruj APP_KEY (po pierwszym deployu z kodem):

```bash
cd /var/www/travel-office/current && php artisan key:generate
```

---

## Krok 13: Storage

```bash
mkdir -p /var/www/travel-office/shared/storage/{app/public,framework/{sessions,views,cache/data},logs,backups}
sudo chown -R deploy:www-data /var/www/travel-office/shared/storage
sudo chmod -R ug+rwx /var/www/travel-office/shared/storage
```

---

## Krok 14: Nginx vhost

```bash
sudo cp /tmp/sor41-setup/deploy/config/nginx/travel-office.conf /etc/nginx/sites-available/travel-office.conf
sudo sed -i 's/app.example.com/TWOJA_DOMENA/g' /etc/nginx/sites-available/travel-office.conf
sudo ln -sf /etc/nginx/sites-available/travel-office.conf /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
```

---

## Krok 15: SSL (Certbot)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d app.example.com
sudo certbot renew --dry-run
```

---

## Krok 16: Supervisor (kolejka)

```bash
sudo apt install -y supervisor
sudo cp /tmp/sor41-setup/deploy/config/supervisor/travel-office-worker.conf /etc/supervisor/conf.d/
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

---

## Krok 17: Sudoers dla deploy

```bash
sudo visudo -f /etc/sudoers.d/travel-office-deploy
# Wklej z deploy/config/sudoers/travel-office-deploy
sudo chmod 440 /etc/sudoers.d/travel-office-deploy
```

---

## Krok 18: Konfiguracja deploy

```bash
cp /tmp/sor41-setup/deploy/config/app.env.example /var/www/travel-office/deploy/config/app.env
nano /var/www/travel-office/deploy/config/app.env
```

Ustaw `APP_URL`, `MONITOR_EMAIL`, `APP_ENV_NAME` (`staging` lub `production`).

Skopiuj skrypty deploy:

```bash
cp -r /tmp/sor41-setup/deploy/* /var/www/travel-office/deploy/
chmod +x /var/www/travel-office/deploy/*.sh
chmod +x /var/www/travel-office/deploy/lib/*.sh
chmod +x /var/www/travel-office/deploy/config/monitoring/*.sh
```

---

## Krok 19: Klucz SSH dla GitHub Actions

Na VPS:

```bash
ssh-keygen -t ed25519 -C "github-actions-deploy" -f ~/.ssh/github_actions -N ""
cat ~/.ssh/github_actions.pub >> ~/.ssh/authorized_keys
cat ~/.ssh/github_actions
```

Prywatny klucz → GitHub Secret `PROD_SSH_KEY` lub `STAGING_SSH_KEY`.

Known hosts (na lokalnej maszynie):

```bash
ssh-keyscan -H app.example.com
```

→ Secret `PROD_SSH_KNOWN_HOSTS`.

Pozostałe secrets: `PROD_SSH_HOST`, `PROD_SSH_USER=deploy`, `PROD_SSH_PORT=22`, `PROD_APP_PATH=/var/www/travel-office`.

---

## Krok 20: GitHub Environments

1. Repo → Settings → Environments → New: `staging`, `production`
2. Production → Required reviewers: 1–2 osoby
3. Dodaj wszystkie secrets (rozdział 7)

---

## Krok 21: Gałąź develop i pierwszy deploy

Na lokalnym repo (maintainer):

```bash
git checkout main
git pull
git checkout -b develop
git push -u origin develop
```

Push `develop` → uruchomi deploy staging (po skonfigurowaniu secrets TEST).

Merge do `main` → deploy prod (z approval).

---

## Krok 22: Cron

```bash
crontab -e
```

Wklej wpisy z [08-cron.md](08-cron.md).

---

## Krok 23: Legacy DB — UFG (jednorazowo)

Po pierwszym deployu na legacy bazie:

```bash
cd /var/www/travel-office/current
php artisan ufg:install
# NIE uruchamiaj pełnego migrate od zera na starej bazie!
```

---

## Krok 24: Weryfikacja końcowa

```bash
curl -sf https://app.example.com/up
curl -sf -o /dev/null -w '%{http_code}' https://app.example.com/admin/login

cd /var/www/travel-office/current
php artisan about
php artisan queue:work --once
sudo supervisorctl status

./deploy/rollback.sh list
./deploy/backup.sh --quick
```

Panel admin: `https://app.example.com/admin`

---

## Krok 25: msmtp (alerty e-mail)

```bash
sudo apt install -y msmtp msmtp-mta mailutils
sudo nano /etc/msmtprc
# patrz 09-monitoring.md
echo "Test alert" | mail -s "SOR41 monitoring test" ops@example.com
```

---

## Troubleshooting

| Problem | Rozwiązanie |
|---------|-------------|
| 502 Bad Gateway | `sudo systemctl status php8.4-fpm`, sprawdź socket w nginx |
| Brak manifestu Vite | CI musi zbudować assety przed rsync |
| Permission denied storage | `chmod -R ug+rwx shared/storage` |
| Queue nie działa | `supervisorctl status`, `QUEUE_CONNECTION=redis` |
| Migrate failed | Staging: `make migrate-check`; legacy: `ufg:install` |
| Deploy failed mid-way | `./deploy/rollback.sh previous` |

---

## Różnice TEST vs PROD

| | VPS TEST | VPS PROD |
|---|----------|----------|
| Domena | staging.example.com | app.example.com |
| Branch | develop | main |
| Migracje | auto (--with-migrate) | ręcznie (workflow input) |
| Approval | brak | required reviewers |
| Secrets | STAGING_* | PROD_* |

Powtórz kroki 1–25 na obu serwerach z odpowiednimi domenami i secrets.
