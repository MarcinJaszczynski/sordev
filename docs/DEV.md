# Środowisko deweloperskie SOR

Jedna ścieżka dev: **PHP + Vite na hoście**, **MySQL na hoście** — bez Dockera.

## Wymagania

- **PHP 8.4** (CI i produkcja; rozszerzenia: mbstring, openssl, pdo_mysql, tokenizer, xml, ctype, json, fileinfo, gd, intl, zip, bcmath)
- MySQL 8.x lub MariaDB 10.6+ (natywnie na hoście, port `3306`)
- Composer, Node.js + npm

### PHP 8.5 na hoście (Fedora/Nobara)

Domyślne PHP w Fedorze 44 to 8.5 — `phpspreadsheet` w locku wymaga `<8.5.0`. Zainstaluj **PHP 8.4 obok** (Remi SCL, nie zastępuje systemowego `php`):

```bash
sudo make install-php84
make php-check          # powinno pokazać php84 i 8.4.x
make composer-install
```

Bez `php84` projekt używa systemowego PHP z ostrzeżeniem; `make composer-install` doda `--ignore-platform-req=php`.

## Pierwsza konfiguracja

```bash
cp .env.example .env
make composer-install
npm ci
sudo mysql < scripts/mysql-bootstrap.sql   # jednorazowo: baza + użytkownik sor
make setup
composer dev
```

Panel admina: http://127.0.0.1:8000/admin

**Środowisko Cursor + AI:** [docs/CURSOR_SETUP.md](CURSOR_SETUP.md) · [docs/WORKSPACE.md](WORKSPACE.md) · [docs/CONSTITUTION.md](CONSTITUTION.md)

Po imporcie dumpu skrypt uruchamia `app:sync-migration-baseline` (pomija migracje Laravel 11 i inne, których tabele/kolumny już są w legacy dumpie), potem `migrate` tylko dla nowych zmian.

## Codzienna praca

```bash
composer dev
```

Uruchamia równolegle:
- `php artisan serve` → http://127.0.0.1:8000
- `php artisan queue:listen`
- `npm run dev` → Vite HMR na :5173

## Baza danych

| Parametr | Wartość |
|----------|---------|
| Host | `127.0.0.1` |
| Port | `3306` |
| Baza | `host378742_sor26` |
| Użytkownik | `sor` |
| Hasło | `sor_secret` |

Źródło danych: dump w katalogu [`deploy/`](../deploy/) (kanoniczny: `host378742_sor26_20260603_095639.sql`).

### Przeładowanie bazy z dumpu

```bash
make setup-fresh
```

### Po `git pull`

```bash
make migrate-check
```

Jeśli są oczekujące migracje:

```bash
php artisan migrate
```

### Moduł UFG (umowy TFG)

Na istniejącej bazie legacy **nie** uruchamiaj pełnego `migrate` od zera — użyj:

```bash
make ufg-install
```

## Makefile — skróty

| Komenda | Opis |
|---------|------|
| `make dev` | Uruchom serwer dev |
| `make setup` | Pierwsza konfiguracja bazy |
| `make setup-fresh` | Reset i ponowny import dumpu |
| `make test` | Testy (`composer test`) |
| `make migrate-check` | Sprawdź oczekujące migracje |
| `make ufg-install` | Instalacja modułu UFG |
| `make deploy-package` | Paczka ZIP do wdrożenia na serwer |
| `make pilot-demo` | Migracje + konto pilota do testów portalu `/pilot` |

## Portal pilota (testy lokalne)

Po `make setup` lub na istniejącej bazie:

```bash
make pilot-demo
# albo bez Makefile:
php artisan pilot:setup-demo --migrate
```

Komenda:
- uruchamia oczekujące migracje (w tym pola raportu pilota na `event_settlements`),
- tworzy/aktualizuje użytkownika pilota,
- przypisuje rolę `pilot` i uprawnienia,
- przypisuje do 3 imprez (`assigned_to`) lub tworzy imprezę demo,
- zakłada aktywne rozliczenia dla tych imprez.

| | |
|--|--|
| URL | http://127.0.0.1:8000/pilot/login |
| Login | `pilot@test.local` |
| Hasło | `pilot123` |

Alternatywnie (seeder legacy): `piotr.zielinski@example.com` / `zielony2024` — po `php artisan db:seed`.

Własne dane konta:

```bash
php artisan pilot:setup-demo --email=moj@pilot.pl --password=sekret --name="Jan Pilot"
```

Testy automatyczne portalu:

```bash
php artisan test --filter=PilotPortalTest
```

Checklist manualny: sekcja **11** w [MANUAL_TESTS.md](MANUAL_TESTS.md).

## Testy

```bash
make test
```

CI używa SQLite (`:memory:`) — lokalny dev używa MySQL z dumpem produkcyjnym.

Checklist testów manualnych (przeglądarka, PDF, pełna baza): **[MANUAL_TESTS.md](MANUAL_TESTS.md)**.

## Wdrożenie na serwer

```bash
make deploy-package
```

Tworzy paczkę ZIP w `deploy/` z aplikacją, dumpem bazy i instrukcją wdrożenia (bez Dockera).

## Rozwiązywanie problemów

**Brak tabeli `contractor_contact`** — uruchom `make setup-fresh` lub `php artisan migrate` (migracja `2026_06_09_100000_rename_contact_contractor_to_contractor_contact` zmienia starą nazwę pivotu).

**Błąd połączenia z MySQL** — sprawdź, czy usługa działa: `systemctl status mysqld` lub `systemctl status mariadb`.

**Stare ustawienia z Dockera** — upewnij się, że `.env` ma `DB_PORT=3306` (nie `3307`) i nie ma odwołań do kontenerów.
