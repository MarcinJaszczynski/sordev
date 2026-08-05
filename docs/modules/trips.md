# Moduł: Zarządzanie Wycieczkami i Kalkulator

## 1. Opis Funkcjonalności
Zarządzanie imprezami grupowymi i indywidualnymi, kalkulator kosztów stałych/zmiennych.

**Mapowanie domenowe:** w dokumentacji używamy „Trip / Wycieczka”; w kodzie Laravel/Filament obowiązuje model **`Event`** (tabela `events`). Nie planujemy rename `Event` → `Trip`.

## 2. Standard Ekranów i Komponenty Filament 4
* **Lista (`ListEvents`)**: filtry/taby (nadchodzące, nierozliczone…), empty state z CTA „Nowa impreza”, szybkie akcje wiersza (program, uczestnicy, finanse, szybki pilot, zmiana statusu slideOver).
* **Podsumowanie (`EditEvent`)**: header actions — Oferta Word, Uczestnicy, Finanse, Zmień status (modal). Przeliczenie `total_cost` przez `RecalculateEventTotalsAction`.
* **Sub-nawigacja imprezy**: program, zadania, uczestnicy, rezerwacje, transport, hotele, pilot, umowy, finanse, dokumenty.
* **Empty states**: brak pilota, brak autokaru, brak uczestników — z jasnym CTA.

## 3. Logika Biznesowa i Automatyzacje
* **Actions** (`app/Actions/Events/`):
  * `ChangeEventStatusAction` + `ChangeEventStatusData` — mutacja statusu w transakcji; emituje `EventStatusChanged` (listenery pod umowy/SMS — przyszłe).
  * `RecalculateEventTotalsAction` + `RecalculateEventTotalsData` — owija `Event::resolvedBaseTotalCost`.
  * `AssignEventPilotAction` + `AssignEventPilotData` — szybkie przypisanie pilota z listy.
* **DTO**: native `readonly` klasy w `app/Data/` (bez Spatie Laravel Data — patrz `04_LARAVEL_STANDARDS.md`).
* Statusy i kolory badge: `Event::getStatusOptions()`, `Event::statusBadgeColor()` (zgodne z `03_UI_DESIGN_SYSTEM.md`).
