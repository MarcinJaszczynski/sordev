# Mapa projektu SOR41

Szybka nawigacja po kodzie — dla Ciebie i dla AI.

## Panele Filament

| Panel | Provider | Ścieżka kodu |
|-------|----------|--------------|
| Admin | `AdminPanelProvider` | `app/Filament/`, `app/Providers/Filament/AdminPanelProvider.php` |
| Pilot | `PilotPanelProvider` | `app/Filament/Pilot/`, `app/Providers/Filament/PilotPanelProvider.php` |

Brand admin: **bprafa**. Dark mode wymuszony wyłączony, motyw jasny, primary Amber.

## Impreza — workflow podstron

`EventResource::getRecordSubNavigation()`:

| Zakładka | Klasa Page |
|----------|------------|
| Podsumowanie | `EventOverview` |
| Dane | `EditEvent` |
| Program | `EditEventProgram` |
| Uczestnicy | `ManageEventParticipants` (hub podzakładek) |
| Rezerwacje | `ManageEventReservations` |
| Transport | `ManageEventTransport` |
| Hotele | `EventHotelPlanning` |
| Pilot | `ManageEventPilot` |
| **Finanse** | `EventFinance` |
| Dokumenty | `ManageEventDocuments` |

**Uczestnicy — podzakładki** (`HasEventParticipantsSubNavigation`):

| Podzakładka | Klasa Page | URL |
|-------------|------------|-----|
| Lista | `ManageEventParticipants` | `/participants` |
| Wpłaty | `ManageEventSettlementPayments` | `/participants/payments` |
| Rezygnacje | `ManageEventResignations` | `/participants/resignations` |
| Portal klienta | `ManageEventClientPortal` | `/participants/portal` |

Lista imprez: `ListEvents` → kolumna finansów: `app/Support/EventListFinanceColumn.php`.

## Serwisy finansowe (wybór)

| Serwis | Odpowiedzialność |
|--------|------------------|
| `UnifiedPriceCalculator` | Ceny szablonu, waluty, zaokrąglenia |
| `EventPriceCalculator` | Kalkulacja imprezy |
| `EventCalculationPresenter` | Prezentacja plan vs rozliczenie |
| `PilotAdvanceService` | Zaliczki pilota |
| `PilotSettlementService` | Rozliczenie pilota |
| `VendorInvoiceProgramPointSync` | KSeF → punkt programu |
| `ProgramPointPaymentStatusResolver` | Status płatności punktu |
| `PendingPaymentAggregator` | Sterta płatności |
| `FinancialReportService` | Raporty |
| `MoneyFormatter` | Format kwot |

## Program imprezy

| Element | Plik |
|---------|------|
| Tabela punktów | `ProgramPointsRelationManager` |
| Zakładki dni | `EditEventProgram` |
| Planer 24h | `EventProgramPlanner` (Livewire) |
| Drzewo | `EventProgramTreeEditor` |
| Kolejność | `EventProgramPointOrderService` |

## Finanse — strony admin

| Strona | Plik |
|--------|------|
| Pulpit finansowy | `FinanceOverviewPage` |
| Sterta płatności | `PendingPaymentsInboxPage` |
| Import płatności bank | `BankPaymentImportPage` |
| Inbox KSeF | `VendorInvoiceInboxPage` |
| Kalendarz operacji | `OperationsCalendarPage` |

## Model Event — pola pilot / udostępnienie

- `shared_with_pilot` — widoczność u pilota
- `pilot_advance_planned_*` / `pilot_advance_paid_*` — zaliczki
- `assigned_to` — przypisany pilot

## Front publiczny

- `resources/views/front/` — oferty, pakiety, rezerwacje
- `pliki/reserwacje/` — prototypy CSS/HTML rezerwacji
- API: `docs/PL/API_V1.md`, `routes/api.php`

## Testy automatyczne

```bash
composer test                    # całość (sqlite memory)
composer test --filter=Event     # fragment
make smoke-check                 # HTTP smoke
make console-audit               # Playwright + konsola JS
```

## Skrypty

| Skrypt | Cel |
|--------|-----|
| `scripts/setup-dev.sh` | Import dumpu MySQL |
| `scripts/smoke-manual-check.php` | Smoke HTTP |
| `scripts/console-audit/run.mjs` | Audyt konsoli |
| `scripts/verify.sh` | CI verify |
| `scripts/cursor-setup.sh` | Bootstrap Cursor |

## Legacy / migracje

- Dump kanoniczny: `deploy/host378742_sor26_*.sql`
- Baseline: `php artisan app:sync-migration-baseline`
- Sprawdzenie: `make migrate-check`
- **Nie** `migrate` na pustej bazie bez `make setup`
