# Konstytucja projektu SOR41

Dokument nadrzędny dla ludzi i AI. Przy większych zmianach **najpierw to**, potem kod.

## Wizja

System obsługi biura turystycznego: imprezy, program, finanse, rozliczenia, pilot, klienci, umowy, KSeF, frontend ofertowy.

Stack: **Laravel 11**, **Filament 3** (panel `/admin`, panel `/pilot`), **MySQL**, **Vite**, **Livewire**.

## Zasady nienaruszalne

### Finanse

- **Nigdy nie upraszczaj logiki turystycznej** — gratis, warianty qty, marże, waluty, noclegi mają swoje reguły w serwisach.
- Każda operacja finansowa musi być **śledzalna** (kto, kiedy, z jakiej kwoty).
- Każda wpłata / płatność — **historia audytu** tam gdzie moduł to wspiera.
- **Nie usuwaj twardo** rekordów finansowych — `soft delete` lub status anulowania.
- Kwoty zawsze z **jawną walutą** (`MoneyFormatter`, nie gołe `number_format` + „PLN” na sztywno).
- Kalkulacja oficjalna: `UnifiedPriceCalculator`, `EventPriceCalculator`, `EventPriceTable` — nie duplikuj logiki w widokach.
- Rozliczenia: `EventSettlement`, `EventSettlementCost`, stos płatności punktów programu.

### Imprezy i program

- Punkt programu (także **podpunkt w secie**) ma pełne finanse i rozliczenie.
- Program: dni, kolejność, sety, godziny (pole „ukryj godziny”).
- Szablon → impreza: kopiowanie z zachowaniem hierarchii i walut.

### Pilot

- Zaliczka: planowana ≠ wypłacona ≠ rozliczenie końcowe (`PilotAdvanceService`).
- Impreza widoczna u pilota dopiero po **udostępnieniu** (`shared_with_pilot`).
- Wymiana walut pilota — **nie jest kosztem** (`PilotCurrencyExchange`).

### Architektura kodu

- Logika biznesowa w **`app/Services/`**, nie w kontrolerach ani w closure Filament na 200 linii.
- Formularze wielokrotnego użytku: **`app/Filament/Forms/`**.
- Wspólne fragmenty tabel/stron: **`Concerns`**, **`Traits`** w Filament.
- Nowe moduły = rozszerzenie istniejących, nie przepisywanie od zera.
- Kompatybilność z **danymi legacy** (dump MySQL, baseline migracji).

### UI (panel admin + pilot)

- Filament + istniejące style w `admin-readability-styles.blade.php`.
- Estetyka: czytelność, spacing, responsywność (tabele przewijane na mobile).
- Listy: wyszukiwanie, filtry, sortowanie; przy większych tabelach — eksport gdzie już jest wzór.
- Stany: loading, błąd, pusty — nie zostawiaj „—” bez kontekstu tam gdzie użytkownik musi wiedzieć dlaczego.

### Bezpieczeństwo

- Autoryzacja: **Filament Shield** + role (`admin`, `ksiegowosc`, pilot).
- Walidacja wejścia na formularzach i w API.
- Pliki: `FileSecurityService`, brak arbitralnego uploadu.
- Sekrety tylko w `.env` — nigdy w repo.

## Mapa modułów (gdzie szukać kodu)

| Moduł | Główne ścieżki |
|-------|----------------|
| Imprezy | `app/Models/Event.php`, `app/Filament/Resources/EventResource/` |
| Program | `ProgramPointsRelationManager`, `EditEventProgram`, `EventProgramPlanner` |
| Kalkulacja cen | `UnifiedPriceCalculator`, `EventPriceTable` widget |
| Finanse / rozliczenia | `EventSettlement*`, `EventFinance`, `FinancialReportService` |
| Umowy / kontrakty | `ContractResource`, `AgreementsRelationManager`, moduł UFG |
| Pilot | `app/Filament/Pilot/`, `PilotAdvanceService`, `PilotSettlementService` |
| KSeF / faktury | `VendorInvoice*`, `VendorInvoiceProgramPointSync` |
| Frontend ofert | `resources/views/front/`, API w `docs/PL/API_V1.md` |

## Testy i jakość

- PHPUnit: `composer test`
- Smoke HTTP: `make smoke-check`
- Audyt JS konsoli: `make console-audit`
- Checklisty ręczne: `docs/MANUAL_TESTS.md`

## Rozwój w przyszłości

Projekt ma wspierać (projektuj z myślą o tym, nie implementuj przedwcześnie):

- multi-firma
- multi-waluta (już częściowo)
- zmiany VAT / regulacji

## Jak AI ma z tego korzystać

Przed implementacją:

1. Przeczytaj ten plik.
2. Przeczytaj `docs/modules/*.md` dotyczący zadania.
3. Przeszukaj istniejący kod — **rozszerz**, nie duplikuj.
4. Minimalny diff, zgodny z konwencją pliku który edytujesz.
