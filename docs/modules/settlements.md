# Moduł: Rozliczenia imprez

## 1. Kanon UI

**Źródło prawdy w panelu admin:** Impreza → **Finanse** (`EventFinance` + nested taby).

| Nested tab | Strona | Zawartość |
|---|---|---|
| Koszty | `EventFinance` | Plan / zapłacone / dokumenty kosztowe |
| Wpłaty | `EventFinanceParticipantPayments` | Rejestr wpłat uczestników |
| Kalkulacja | `EventCalculation` | Cena z programu i marża |
| Gotówka i waluty | `EventFinancePilotCash` | Zaliczka pilota, wymiany |
| Dok. rozliczenia | `EventFinanceSettlementDocuments` | Załączniki rozliczenia |

**Nie używać** `/admin/event-settlements` jako kanonu UI — `EventSettlementResource` jest ukryty w nawigacji; stare URL-e redirectują do hubu Finanse / pulpitu finansowego.

Model domenowy `EventSettlement` nadal istnieje (dane), ale **ekranem pracy biura jest EventFinance**.

## 2. Tworzenie rozliczenia

**Kanon (impreza z szablonu):** `Event::createFromTemplate` → kopia programu → `findOrCreateActiveForEvent` + `refreshActiveSettlementCosts` (plan kosztów z punktów programu). Finanse otwierają się już z danymi.

**GET Finanse** nie tworzy settlementu (brak side-effectu odczytu). Empty state + „Utwórz rozliczenie” tylko gdy brak draftu (legacy / usunięty).

**Mutacje** w hubie (grupy, wpłaty kosztowe…) używają `ensureSettlement()` / `findOrCreateActiveForEvent`.

## 3. Actions

Write-pathy finansowe: `app/Actions/Finance/` (`UpdateSettlementCostPlanAction`, `RecordSettlementCostPaymentAction`, `AttachSettlementCostDocumentAction`, …).
