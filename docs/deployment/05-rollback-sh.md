# 5. rollback.sh — rollback release

Plik: [`deploy/rollback.sh`](../../deploy/rollback.sh)

## Użycie

```bash
./deploy/rollback.sh list [staging|production]
./deploy/rollback.sh previous [staging|production]
./deploy/rollback.sh to <release_name|numer> [staging|production]
```

## Komendy

### list — lista releases

```bash
$ ./deploy/rollback.sh list production

Release                    Aktywny  Katalog
────────────────────────────────────────────────────────────
  1) 20260722_143022_abc1234  [*]  /var/www/travel-office/releases/20260722_143022_abc1234
  2) 20260722_120000_def5678  [ ]  /var/www/travel-office/releases/20260722_120000_def5678

* = aktualny current
```

### previous — poprzedni release

Przełącza `current` na release bezpośrednio poprzedni w chronologii (drugi na liście).

```bash
./deploy/rollback.sh previous production
```

### to — konkretny release

Po nazwie katalogu:

```bash
./deploy/rollback.sh to 20260722_120000_def5678 production
```

Po numerze z listy:

```bash
./deploy/rollback.sh to 2 production
```

## Co robi rollback

1. Weryfikuje istnienie release i pliku `artisan`
2. `ln -sfn releases/TARGET current`
3. Reload PHP-FPM
4. `queue:restart` + restart Supervisor workers
5. Health check `/up`
6. Wpis w `shared/logs/deploy.log`
7. Alert e-mail (jeśli skonfigurowany)

## Czego rollback NIE robi

- **Nie cofa migracji bazy** — schemat pozostaje z nowszej wersji
- **Nie usuwa nowego release** — pozostaje w `releases/` do analizy
- **Nie przywraca .env** — `shared/.env` jest wspólny

### Cofnięcie migracji (ręcznie, wyjątkowo)

```bash
cd /var/www/travel-office/current
php artisan migrate:rollback --step=1 --force
```

Wykonuj tylko gdy wiesz, że migracja jest odwracalna i bezpieczna dla danych produkcyjnych.

## Rollback jednym poleceniem

```bash
ssh deploy@prod.example.com 'cd /var/www/travel-office && ./deploy/rollback.sh previous production'
```

## Kiedy używać

- Deploy przeszedł CI, ale aplikacja ma błąd runtime
- Regresja UI po wdrożeniu
- Błąd konfiguracji cache (szybsze niż ponowny deploy)

## Kiedy NIE używać

- Błąd wymaga nowej poprawki w kodzie → hotfix + deploy
- Migracja nieodwracalna już wykonana → rollback kodu może być niespójny ze schematem DB
