# Smoke testy biurowe — impreza od zapytania do rozliczenia

Dokument dla **zwykłych użytkowników biura** (nie dla programistów).  
Służy do sprawdzenia, czy cały proces obsługi imprezy działa „od A do Z”.

**Plik do wypełniania w Excelu:** [`smoke_biuro_impreza.csv`](./smoke_biuro_impreza.csv)  
(otwórz w Excelu / LibreOffice Calc → zapisz jako `.xlsx` jeśli wygodniej)

**Wydruk / PDF:** [`smoke_biuro_impreza.pdf`](./smoke_biuro_impreza.pdf)

Techniczna, szczegółowa checklista P0/P1 (dla IT): [`manual_user_paths.csv`](./manual_user_paths.csv)

---

## Jak robić te testy (przeczytaj raz)

### Cel
Przejść **jedną testową imprezę** przez cały cykl:

```
Zapytanie → Oferta → Wstępna rezerwacja → Potwierdzona
→ (wyjazd / operacje) → Do rozliczenia → Rozliczona
```

Nie testujemy każdej drobnej funkcji osobno — sprawdzamy, że **główna ścieżka biura** działa bez błędów.

### Co potrzebujesz
1. Konto do **panelu biura** (admin).
2. Opcjonalnie: konto **portalu klienta** i **portalu pilota** (albo podgląd z biura).
3. **Szablon imprezy** z programem (albo gotowość do ręcznego dodania kilku punktów programu).
4. Czas: ok. **45–90 minut** na pełną ścieżkę.
5. Imprezę testową oznacz w nazwie, np. `TEST SMOKE 2026-08-10`, żeby nie pomylić z produkcją.

### Zasady
- Idź **po kolei** (kolumna Nr w CSV). Nie skacz do rozliczenia, jeśli nie ma umowy i wpłat.
- Po każdym kroku zaznacz w CSV: **OK / Błąd / Pominięte**.
- Przy błędzie: zrób **zrzut ekranu**, zapisz w kolumnie **Uwagi** co kliknąłeś i co zobaczyłeś (komunikat, pusty ekran, zła liczba).
- **Nie myl** dwóch rzeczy w Finanse:
  - **Kalkulacja** = cena oferty (ile klient ma zapłacić / marża planu).
  - **Koszty** = rozliczenie rzeczywiste (ile biuro zapłaciło dostawcom).
- Statusy zmieniaj świadomie — system przy zmianie statusu często tworzy **zadania** i może wysłać mail/SMS.

### Co uznajemy za sukces całego smoke
Wszystkie kroki oznaczone **OK** (albo świadomie **Pominięte** z uzasadnieniem).  
Impreza kończy w statusie **Rozliczona**.  
Wpłaty, koszty i gotówka pilota się zgadzają „na oko” (brak oczywistych niespójności).

### Gdy coś nie działa
1. Odśwież stronę i spróbuj raz jeszcze.
2. Sprawdź, czy jesteś w właściwej imprezie (nazwa TEST…).
3. Sprawdź, czy zrobiłeś krok wcześniejszy (np. umowy bez uczestników płatnych).
4. Zapisz w CSV i zgłoś do IT z numerem kroku (np. S-07).

---

## Mapa ekranów (gdzie szukać)

### Menu główne
| Gdzie | Po co |
|---|---|
| **Obsługa imprez → Imprezy** | Lista i karta imprezy |
| **Obsługa imprez → Ścieżka oferty** | Tablica statusów (kanban) |
| **Obsługa imprez → Kalendarz** | Widok operacyjny |
| **Szablony imprez → Szablony** | Tworzenie imprezy ze szablonu |
| **Finanse** (grupa w menu) | Skrzynka płatności, import bankowy, wnioski o fakturę itd. |

### W karcie imprezy (zakładki u góry)
**Podsumowanie** → **Program** → **Uczestnicy** → **Operacje** → **Finanse** → **Dokumenty**

| Zakładka | Podzakładki |
|---|---|
| **Uczestnicy** | Lista, Rezygnacje, Portal klienta, Ubezpieczenia |
| **Operacje** | Zadania, Rezerwacje, Transport, Hotele, Pilot |
| **Finanse** | Koszty, Wpłaty, Kalkulacja, Gotówka pilota, Dok. rozliczenia |
| **Dokumenty** | Umowy, Pliki, Historia |

---

## Skrót faz (kolejność obowiązkowa)

1. **Utwórz imprezę** (ze szablonu najlepiej) — status *Zapytanie*
2. **Program + Kalkulacja** — sensowne ceny
3. Status **Oferta** + ewentualnie Oferta Word
4. **Rezerwacje / Transport** — blokada dostawców
5. Status **Potwierdzona**
6. **Uczestnicy** (płatni)
7. **Umowy** + harmonogram wpłat + link
8. **Portal klienta** (dostęp / podgląd)
9. **Wpłaty** (przynajmniej jedna)
10. **Pilot** — przypisz + „Udostępnij pilotowi”
11. Status **Do rozliczenia**
12. **Koszty** + domknięcie wpłat + gotówka pilota
13. Status **Rozliczona**

Szczegółowe kroki z polami do odhaczania — w pliku CSV.
