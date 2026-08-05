# 00. Wizja Produktu i Filozofia Projektu (TourERP / BusVibe Core)

## 1. Cel Systemu
Budowa zintegrowanego, bezkonkurencyjnego systemu klasy ERP/CRM dedykowanego dla biur podróży, ze szczególnym uwzględnieniem **imprez grupowych (szkolnych, zakładowych)** oraz **turystyki indywidualnej**. System ma spinać serwis sprzedażowy (frontend storefront/kalkulator), silnik rezerwacji, automatyzację ofertowania, logistykę (autokary, piloci, hotele), finanse (KSeF, rozliczenia imprez, prowizje) oraz integrację z modelami AI wspierającymi tworzenie programów i kalkulacji.

## 2. Filozofia Projektu
* **Single Source of Truth**: Wszelkie dane dotyczące rezerwacji, finansów i logistyki znajdują się w jednym miejscu.
* **Modal-First & Fast Execution**: Brak zbędnego przeładowywania stron. Użytkownik biurowy wykonuje operacje w maks. 2 kliknięciach.
* **Deterministic Financials**: Finanse i kalkulacje są deterministyczne. AI jedynie podpowiada i generuje szkice, ale wyliczenia bazują na ścisłych algorytmach i wzorach.
* **Automation-Driven Logistics**: Każda zmiana statusu imprezy automatycznie wyzwala procesy pomocnicze (zadania dla pilotów, generowanie umów, powiadomienia SMS/Email).

## 3. Persony Użytkowników
1. **Agent / Handlowiec (Sales)**: Tworzy oferty, prowadzi rozmowy z opiekunami grup/szkół, dostosowuje kalkulacje.
2. **Koordynator Imprez / Logistyk (Operations)**: Przydziela autokary, obiekty noclegowe, przewodników i pilotów. Kontroluje harmonogram.
3. **Księgowy / Finansista (Finance)**: Rozlicza zaliczki, wydatek zaliczkowy pilota, wystawia faktury VAT-Marża, obsługuje KSeF i płatności PayU/TPay.
4. **Opiekun Grupy / Nauczyciel (Client Lead)**: Korzysta z portalu klienta, uzupełnia listę uczestników, diet, zbiera zgody.
5. **Pilot Wycieczki (Field Ops)**: Dostęp mobilny – podgląd harmonogramu, listy obecności, wgrywanie paragonów/kosztów na żywo.
