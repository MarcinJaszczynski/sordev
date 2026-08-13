# 04. Standardy Architektoniczne Laravel 11 / 12

## 1. Reguła warstw (obowiązująca)

Kierunek zależności: **UI → Action/Service → Model**. Nigdy odwrotnie.

| Warstwa | Gdzie | Odpowiedzialność | Nie robi |
|---------|--------|------------------|----------|
| **UI** | `app/Filament/…`, `app/Livewire/…` | formularze, nawigacja, prezentacja, wywołanie Action/Service | logiki biznesowej, SQL finansowego, kalkulacji cen |
| **Action** | `app/Actions/…` + `app/Data/…` | **jeden write use-case**: walidacja wejścia (Data), transakcja, orchestracja | render UI, import klas Filament |
| **Service** | `app/Services/…` | kalkulacje, sync, PDF, importy, logika wielokrotnego użytku | zależność od Filament/Livewire, nawigacja URL panelu |
| **Model** | `app/Models/…` | relacje, casty, proste scopes, persistencja | `Schema::` w runtime, importy Filament, „god object” z importem/sync/recalc |

### Zasady praktyczne

1. **Nowy zapis krytyczny** (finanse, status imprezy, uczestnik, rezerwacja, płatność) → `Action` + `readonly Data` + `DB::transaction()` wewnątrz Action.
2. **Kalkulacje / odczyty złożone** → `Service`. Odczyt **nie** powinien mieć side-effectów zapisu (np. `findOrCreate` przy GET — jawny write albo osobny Action).
3. **Filament / Livewire** woła Action lub Service — nie duplikuje SQL ani formuł cenowych w komponencie.
4. **Service nie importuje** `App\Filament\…`. URL-e panelu: `App\Support\AdminPanelUrls` (Support może znać Filament).
5. **Źródło prawdy cen szablonu:** `EventTemplateCalculationEngine` (persist/API) + `EventTemplateUiCalculationService` (format UI z punktami/hotel_structure) → `UnifiedPriceCalculator` (normalizacja + zapis). Widgety Filament tylko prezentują / zlecają.
6. **Dokumenty pakietowe:** fasada `EventDocumentGeneratorService` (`downloadPackage`, `downloadFullPackageZip`).
7. **Import/totals settlement:** `EventSettlementImportService`, `EventSettlementTotalsService` (+ cienkie wrappery na modelu).
8. **Odczyt finansów:** `EventFinanceOverviewService` / workflow summary **nie tworzą** settlement. Create: przy **tworzeniu imprezy z szablonu** (write path) oraz jawnych mutacjach / „Utwórz rozliczenie” — **nie** przy samym GET Finanse.
9. **Autoryzacja finance:** `EventPolicy::manageFinance` + `EventSettlementPolicy` (recordParticipantPayment / recordCostPayment / updateCostPlan / attachCostDocument) — egzekwowane w Actions.

Wzór Action: `RecordParticipantPaymentAction` + `RecordParticipantPaymentData`.

## 2. Wzorce Projektowe

* **Actions (Custom invokable)**: Operacje modyfikujące stan systemu w klasach `App\Actions\...` (np. `ChangeEventStatusAction`, `RecalculateEventTotalsAction`). Filament / Livewire woła Action — nie duplikuje SQL w komponentach.
* **DTO (Data Transfer Objects)**: Wejścia do Actions przez silnie typowane **native `readonly` klasy** w `App\Data\...`.  
  **Uwaga:** Spatie Laravel Data **nie** jest obecnie w projekcie — nie dodajemy pakietu „na zapas”. Gdy zajdzie potrzeba (walidacja DTO, nestowanie), dopiero wtedy rozważamy `spatie/laravel-data`.
* **Nazewnictwo domenowe:** w docs „Trip” = w kodzie **`Event`**. Events domenowe: `EventStatusChanged` (nie `TripStatusChanged`).
* **Repositories / Query Builders**: Złożone zapytania SQL w dedykowanych Eloquent Custom Query Builders / Services.
* **Events & Listeners**: `EventStatusChanged` → `HandleEventStatusChanged` → `EventStatusAutomationService` (zadania biurowe + `SmsChannelInterface`). Nie dodawaj pustych stubów bez procesu.
* **Płatności online**: `config/payments.php` + `App\Services\Payments\*` (driver `fake|tpay|payu`). Księgowanie przez `CompleteOnlinePaymentAction`.
* **Przypomnienia wpłat**: `payments:send-reminders` + `PaymentReminderCadenceService` + szablony `MailTemplate` / `MailTemplateService`.
* **Occupancy hotel**: `EventHotelOccupancyService` (read-model) na stronie Hotele.
* **KSeF outbound**: stub `App\Services\Invoices\KsefOutboundService` (`invoices.ksef_outbound_enabled`).
* **Audit imprezy**: zakładka Historia (`EventAuditLogPage` → `HistoryRelationManager` / `EventHistory`).

## 3. Zasady Bezpieczeństwa i Transakcyjności

* Każda operacja zmieniająca finanse LUB stan rezerwacji / status imprezy MUSI być wywoływana w `DB::transaction()` (zwykle wewnątrz Action).
* Strict Types (`declare(strict_types=1);`) w każdym nowym pliku PHP (Actions, Data, Events).
* Finanse: unikaj float w nowych obliczeniach — preferuj centy całkowite / `bcmath` / istniejący `MoneyFormatter` do prezentacji.
