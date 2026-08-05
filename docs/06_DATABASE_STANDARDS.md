# 06. Standardy Bazy Danych i Modelowania

## 1. Klucze i Typy Danych
* Wszystkie tabele używają `ULID` lub `UUID` jako kluczy głównych dla ułatwienia synchronicznej integracji API i bezpieczeństwa URL.
* Kwoty pieniężne przechowywane są jako `BIGINT` w groszach (np. 1500.50 PLN = `150050`).

## 2. Indeksowanie
* Indeksy złożone dla kluczy obcych i pól statusów (`trip_id`, `status`, `created_at`).
* Indeksy typu Full-Text dla nazw klientów, szkół oraz tytułów wycieczek.
