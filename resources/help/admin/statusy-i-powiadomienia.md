---
slug: statusy-i-powiadomienia
title: Zmiana statusu i powiadomienia
summary: Jak bezpiecznie zmieniać status imprezy i co wtedy dzieje się automatycznie.
category: Imprezy
tags: status, sms, e-mail, zadania
sort: 40
---

## Cel

Przesunąć imprezę na właściwy etap bez gubienia obowiązków biura i bez przypadkowych powiadomień do klienta.

## Kroki

1. Otwórz imprezę albo **Ścieżkę oferty**.
2. Użyj akcji **Zmień status** (modal / drag na tablicy).
3. Podaj powód, jeśli system o to prosi (szczególnie przy anulacji).
4. Sprawdź **Zadania** — po zmianie statusu mogą pojawić się zadania biurowe.
5. Sprawdź, czy klient ma e-mail/telefon, gdy oczekujesz SMS lub maila.

## Automatyzacje (skrót)

- Przy **Oferta** i **Potwierdzona** — możliwe SMS i e-mail do klienta.
- Zadania biurowe trafiają do ról admin / biuro.
- Przy ważnych statusach system może zrobić snapshot imprezy (kopia stanu).

## Wynik

Status na liście i w ścieżce jest aktualny; biuro ma zadania; klient dostał komunikat (jeśli dane kontaktowe są kompletne).

## Typowe błędy

- Ręczna zmiana wielu statusów „do przodu” bez umów i wpłat.
- Anulacja bez przejścia przez **Do anulacji**, gdy proces wymaga weryfikacji.
- Oczekiwanie powiadomienia przy pustym `client_email` / `client_phone`.
