# Ścieżka biura (1 strona)

Przepływ imprezy od zapytania do rozliczenia — gdzie klikać w panelu admin.

```
Zapytanie → Oferta → Potwierdzenie → Operacje → Rozliczenie
```

## 1. Zapytanie (`inquiry`)

| Co robić | Gdzie |
|---|---|
| Nowa impreza (z szablonu lub pusta) | Imprezy → Utwórz |
| Dane klienta, daty, liczba osób | Impreza → **Dane** |
| Śledzenie ścieżki sprzedażowej | **Ścieżka oferty** (tablica statusów) |

## 2. Oferta (`offer`)

| Co robić | Gdzie |
|---|---|
| Przenieś status na „Oferta” | Ścieżka oferty / Zmień status |
| Dopracuj program i ceny | **Program**, **Kalkulacja** / **Finanse** |
| Wyślij ofertę Word | Impreza → Oferta Word |
| Klient dostaje SMS/mail (gdy podany telefon/e-mail) | automatycznie przy statusie Oferta |

## 3. Potwierdzenie (`provisional_reservation` → `confirmed`)

| Co robić | Gdzie |
|---|---|
| Rezerwacja wstępna (hotel/bus) | **Rezerwacje**, **Transport**, **Hotele** |
| Potwierdzenie imprezy | status **Potwierdzona** |
| Umowy i linki publiczne | **Umowy** |
| Wpłaty uczestników | **Finanse → Wpłaty** |
| Portal klienta / pilot | Uczestnicy, przypisanie pilota |

## 4. Operacje (w trakcie)

| Co robić | Gdzie |
|---|---|
| Kalendarz i zadania | **Kalendarz**, **Zadania** |
| Listy / dokumenty pakietowe | **Dokumenty** |
| Program dnia, transport, hotel | zakładki operacyjne imprezy |

## 5. Rozliczenie (`to_settle` → `settled`)

| Co robić | Gdzie |
|---|---|
| Status „Do rozliczenia” | Ścieżka oferty / Zmień status |
| Koszty, faktury, gotówka pilota | **Finanse** (podzakładki) |
| Domknięcie | status **Rozliczona** |

## Szybkie skróty

- **Ścieżka oferty** = tablica statusów sprzedażowych (nie „pipeline”).
- **Finanse imprezy** = jedno miejsce na marżę, koszty i wpłaty.
- Klon szablonu: lista/edycja szablonu → **Klonuj** (kopiuje pivota bez wyłączania FK).
