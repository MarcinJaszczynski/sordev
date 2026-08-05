# 04. Standardy Architektoniczne Laravel 11

## 1. Wzorce Projektowe
* **Actions (Custom invokable)**: Operacje modyfikujące stan systemu w klasach `App\Actions\...` (np. `ChangeEventStatusAction`, `RecalculateEventTotalsAction`). Filament / Livewire woła Action — nie duplikuje SQL w komponentach.
* **DTO (Data Transfer Objects)**: Wejścia do Actions przez silnie typowane **native `readonly` klasy** w `App\Data\...`.  
  **Uwaga:** Spatie Laravel Data **nie** jest obecnie w projekcie — nie dodajemy pakietu „na zapas”. Gdy zajdzie potrzeba (walidacja DTO, nestowanie), dopiero wtedy rozważamy `spatie/laravel-data`.
* **Nazewnictwo domenowe:** w docs „Trip” = w kodzie **`Event`**. Events domenowe: `EventStatusChanged` (nie `TripStatusChanged`).
* **Repositories / Query Builders**: Złożone zapytania SQL w dedykowanych Eloquent Custom Query Builders / Services.
* **Events & Listeners**: Zmiany stanu (np. `EventStatusChanged`) mogą wyzwalać asynchroniczne procesy (umowy, SMS) — listenery dodajemy gdy proces jest zaimplementowany, nie jako puste stuby biznesowe.

## 2. Zasady Bezpieczeństwa i Transakcyjności
* Każda operacja zmieniająca finanse LUB stan rezerwacji / status imprezy MUSI być wywoływana w `DB::transaction()` (zwykle wewnątrz Action).
* Strict Types (`declare(strict_types=1);`) w każdym nowym pliku PHP (Actions, Data, Events).
* Finanse: unikaj float w nowych obliczeniach — preferuj centy całkowite / `bcmath` / istniejący `MoneyFormatter` do prezentacji.
