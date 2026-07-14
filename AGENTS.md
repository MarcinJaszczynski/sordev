# SOR41 — instrukcje dla agenta AI (Cursor)

Projekt: **system biura turystycznego bprafa**  
Ścieżka: `/home/mm/Dokumenty/praca/sor41`

## Stack (nie zakładaj innego)

| Warstwa | Technologia |
|---------|-------------|
| Backend | PHP 8.4, **Laravel 12** |
| Panel | **Filament 3** + Shield (uprawnienia) |
| UI dynamiczny | Livewire 3, Alpine.js |
| CSS | Tailwind 4 + Vite 6 |
| Baza dev | **MySQL** `host378742_sor26` @ `127.0.0.1:3306` |
| Testy | **Pest** + PHPUnit (`composer test` → sqlite :memory:) |
| PDF/Excel | dompdf, maatwebsite/excel, phpword |

## URLe lokalne

| Panel | URL |
|-------|-----|
| Admin | http://127.0.0.1:8000/admin |
| Pilot | http://127.0.0.1:8000/pilot/login |
| Portal klienta | http://127.0.0.1:8000/portal/login |
| Front ofert | http://127.0.0.1:8000/ |

Konta testowe: `make pilot-demo`, audyt konsoli: `make console-audit` (konto `console-audit@local`).

## Uruchomienie

```bash
make setup      # pierwszy raz (MySQL + dump)
composer dev    # serwer + queue + Vite
```

Po `git pull`: `make migrate-check`. Moduł UFG: `make ufg-install`.

## Architektura — gdzie co pisać

```
app/Services/           ← logika biznesowa (TU, nie w Resource)
app/Support/            ← helpery (MoneyFormatter, nawigacja)
app/Filament/Resources/ ← CRUD Filament (admin)
app/Filament/Pages/     ← strony niestandardowe (admin)
app/Filament/Pilot/     ← panel pilota
app/Filament/Forms/     ← wspólne pola formularzy
app/Livewire/           ← komponenty Livewire (program, drzewo)
resources/views/filament/  ← Blade panelu
resources/views/front/     ← front publiczny
tests/Feature|Unit/     ← Pest
docs/                   ← dokumentacja (CZYTAJ przed większą zmianą)
```

## Moduły biznesowe → dokumentacja

| Temat | Plik |
|-------|------|
| Zasady nadrzędne | `docs/CONSTITUTION.md` |
| Imprezy | `docs/modules/02-imprezy.md` |
| Finanse | `docs/modules/03-finanse.md` |
| Pilot | `docs/modules/04-pilot.md` |
| Program | `docs/modules/05-program.md` |
| Architektura | `docs/modules/06-architektura.md` |
| UI | `docs/modules/07-ui.md` |
| Mapa plików | `docs/PROJECT_MAP.md` |
| Dev / baza | `docs/DEV.md` |
| Testy ręczne | `docs/MANUAL_TESTS.md` |

## Nawigacja panelu admin

Grupy (`app/Support/FilamentNavigation.php`):

- **Obsługa imprez** — EventResource, szablony, program
- **Finanse** — rozliczenia, faktury KSeF, sterta płatności
- **Zarządzanie** — statystyki wykonawcze (ograniczone role)
- **Kontakty**, **Słowniki**, **System**

## Finanse — reguły twarde

1. Kwoty: `App\Support\MoneyFormatter` — **nigdy** `number_format` + hardcoded `PLN`.
2. Kalkulacja oficjalna: `UnifiedPriceCalculator`, widget `EventPriceTable`.
3. Lista imprez — kolumna Finanse: `EventListFinanceColumn` + filtr waluty.
4. Rozliczenia: `EventSettlement` / `EventSettlementCost` — statusy `draft|active|pilot_settled`.
5. Nie upraszczaj: qty, gratis, marże, waluty obce, noclegi.
6. Nie usuwaj twardo rekordów finansowych.

## Filament — pułapki w tym projekcie

- **Nie używaj** `deferLoading()` na RelationManagerach (błędy Livewire).
- W komórkach tabel: `x-on:click.stop="$wire...."` zamiast `wire:click`.
- Sprawdź `Schema::hasColumn` / `Schema::hasTable` przy legacy DB.
- Uprawnienia: Shield — `canAccess()`, role `admin`, `ksiegowosc`, `super_admin`.

## Zanim napiszesz kod

1. Przeczytaj `docs/CONSTITUTION.md`.
2. Znajdź istniejący serwis/wzorzec — **rozszerz**, nie duplikuj.
3. Minimalny diff, styl jak w otaczającym pliku.
4. Commity **tylko na prośbę** użytkownika.

## Po zmianach

| Zmiana | Komenda |
|--------|---------|
| Logika PHP | `composer test` lub `composer test --filter=NazwaTestu` |
| HTTP / panel | `make smoke-check` |
| UI / JS | `make console-audit` |
| Nowy flow UI | wpis w `docs/MANUAL_TESTS.md` |

## Prompty gotowe

Katalog `prompts/` — `feature.md`, `bugfix.md`, `architecture.md`, `review.md`.

## Język

Użytkownik komunikuje się po polsku — odpowiedzi po polsku.
