# 04. Standardy Architektoniczne Laravel 11

## 1. Wzorce Projektowe
* **Actions (Spatie / Custom)**: Wszystkie operacje modyfikujące stan systemu muszą znajdować się w klasach Actions (np. `CalculateTripCostAction`, `ConfirmReservationAction`, `IssueKsefInvoiceAction`).
* **DTO (Data Transfer Objects)**: Przekazywanie danych do akcji wyłącznie poprzez silnie typowane DTO (Spatie Laravel Data).
* **Repositories / Query Builders**: Złożone zapytania SQL zapakowane w Dedykowane Eloquent Custom Query Builders.
* **Events & Listeners**: Zmiany stanu (np. `TripStatusChanged`) wyzwalają asynchroniczne zdarzenia obsługiwane w kolejkach (Redis/Horizon).

## 2. Zasady Bezpieczeństwa i Transakcyjności
* Każda operacja zmieniająca finanse LUB stan rezerwacji MUSI być wywoływana w `DB::transaction()`.
* Strict Types (`declare(strict_types=1);`) w każdym pliku PHP.
