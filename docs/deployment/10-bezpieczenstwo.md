# 10. Bezpieczeństwo

## SSH Keys

### Wygenerowanie klucza deploy (dedykowany)

```bash
ssh-keygen -t ed25519 -C "deploy@sor41" -f ~/.ssh/id_ed25519_deploy
```

### Konfiguracja serwera

W `/etc/ssh/sshd_config`:

```
PermitRootLogin no
PasswordAuthentication no
PubkeyAuthentication yes
AllowUsers deploy
MaxAuthTries 3
```

```bash
sudo systemctl restart sshd
```

### Klucze developerów

Każdy developer — osobny klucz w `~deploy/.ssh/authorized_keys` z komentarzem identyfikującym osobę.

## Firewall (UFW)

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
# Opcjonalnie: ogranicz SSH do IP biura
# sudo ufw allow from 203.0.113.0/24 to any port 22
sudo ufw enable
sudo ufw status verbose
```

## Fail2Ban

```bash
sudo apt install fail2ban
sudo cp deploy/config/fail2ban/jail.local /etc/fail2ban/jail.local
sudo systemctl enable fail2ban
sudo systemctl restart fail2ban
sudo fail2ban-client status sshd
```

Chroni: SSH, nginx auth, nginx limit-req, botsearch.

## Użytkownik deploy

```bash
sudo adduser --disabled-password --gecos "" deploy
sudo usermod -aG www-data deploy
```

- Bez hasła — tylko logowanie kluczem SSH
- Właściciel plików aplikacji
- PHP-FPM i Nginx działają jako `www-data`

## Uprawnienia katalogów

```bash
# Aplikacja
sudo chown -R deploy:www-data /var/www/travel-office
sudo find /var/www/travel-office/shared/storage -type d -exec chmod 775 {} \;
sudo find /var/www/travel-office/shared/storage -type f -exec chmod 664 {} \;

# Release bootstrap/cache
chmod -R ug+rwx /var/www/travel-office/current/bootstrap/cache

# .env — tylko właściciel
chmod 600 /var/www/travel-office/shared/.env
```

## Ograniczenie sudo

```bash
sudo visudo -f /etc/sudoers.d/travel-office-deploy
# Wklej zawartość deploy/config/sudoers/travel-office-deploy
```

Deploy może **tylko**:
- `systemctl reload/restart php8.4-fpm`
- `supervisorctl restart/status travel-office-worker:*`

## Brak logowania root

- `PermitRootLogin no`
- Admin pracuje przez `sudo` na koncie osobistym lub `deploy`
- Klucz root usunięty z authorized_keys

## Bezpieczeństwo aplikacji (.env)

Produkcja — wymagane:

```
APP_ENV=production
APP_DEBUG=false
DEBUGBAR_ENABLED=false
LOG_LEVEL=error
```

## Nginx — dodatkowe nagłówki

W [`travel-office.conf`](../../deploy/config/nginx/travel-office.conf):
- `X-Frame-Options`
- `X-Content-Type-Options`
- `Referrer-Policy`

## SSL/TLS

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d app.example.com
sudo certbot renew --dry-run
```

## GitHub / CI

- Osobne klucze SSH staging vs prod
- Secrets nigdy w repo
- Branch protection na `main`
- Required reviewers dla production environment
- Deploy keys read-only

## Aktualizacje systemu

```bash
sudo apt update && sudo apt upgrade -y
sudo unattended-upgrades --dry-run
```

Włącz automatyczne security updates:

```bash
sudo dpkg-reconfigure -plow unattended-upgrades
```

## Checklist bezpieczeństwa

- [ ] Root login wyłączony
- [ ] Hasła SSH wyłączone
- [ ] UFW aktywny
- [ ] Fail2Ban aktywny
- [ ] SSL certyfikat ważny
- [ ] APP_DEBUG=false
- [ ] .env chmod 600
- [ ] Osobne klucze deploy staging/prod
- [ ] Branch protection main/develop
- [ ] Backup działa (test restore)
