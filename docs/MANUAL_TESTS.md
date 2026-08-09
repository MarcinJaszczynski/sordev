# Testy manualne SOR

Checklist do weryfikacji aplikacji po wdrożeniu, większej zmianie lub `git pull`.  
Testy automatyczne: `make test` (154 testy, SQLite). Ten dokument uzupełnia obszary wymagające przeglądarki, PDF i pełnej bazy legacy.

## Przygotowanie

| Krok | Komenda / URL |
|------|----------------|
| Środowisko | `make setup` lub `make setup-fresh` — patrz [DEV.md](DEV.md) |
| Dev server | `composer dev` → http://127.0.0.1:8000 |
| Smoke automatyczny | `make smoke-check` (HTTP + panel + API na działającym serwerze) |
| **Smoke konsoli** | `make console-audit` — błędy JS w panelach admin + pilot (Playwright) |
| Panel admin | http://127.0.0.1:8000/admin |
| Konto testowe | użytkownik z rolą `admin` lub `panel_user` z odpowiednimi uprawnieniami Shield |
| Przeglądarka | wyłącz rozszerzenia typu alerabat.com na localhost (fałszywe błędy w konsoli) |

**Legenda:** ✅ = OK | ❌ = błąd | ⏭ = pominięto (brak danych / uprawnień)

---

## 0. Smoke (5 min)

| # | Test | Oczekiwany wynik | Auto |
|---|------|------------------|------|
| 0.1 | Strona główna `/{region}` (np. `/warszawa`) | 200, lista ofert | częściowo |
| 0.2 | Logowanie `/admin` | panel Filament | — |
| 0.3 | Dashboard — widgety | liczniki bez błędu 500 | częściowo |
| 0.4 | `make test` | 154 passed, 0 failed | ✅ |
| 0.5 | `make migrate-check` | brak oczekujących migracji | ✅ |
| 0.6 | `make console-audit` | brak błędów `console.error` na wygenerowanych URL panelu | ✅ |

### Smoke konsoli (Playwright)

Wymaga działającego serwera (`composer dev`), zbudowanych assetów oraz Chromium:

```bash
npm install
npm run console-audit:install   # jednorazowo: pobiera Chromium
make console-audit              # bootstrap kont + URL + Playwright
```

Opcjonalnie tylko jedna grupa nawigacji:

```bash
php artisan app:console-audit-urls --group=events
node scripts/console-audit/run.mjs --group=events
```

Zmienne środowiskowe (domyślne z seedów):

| Zmienna | Domyślnie |
|---------|-----------|
| `APP_URL` | `http://127.0.0.1:8000` |
| `CONSOLE_AUDIT_ADMIN_EMAIL` | `console-audit@local` |
| `CONSOLE_AUDIT_ADMIN_PASSWORD` | `console-audit` |
| `CONSOLE_AUDIT_PILOT_EMAIL` | `pilot@test.local` |
| `CONSOLE_AUDIT_PILOT_PASSWORD` | `pilot123` |

Raport JSON: `storage/console-audit/report.json`. Lista URL: `storage/console-audit/urls.json`.

Pilot: przed audytem panelu pilota uruchom `make pilot-demo`, żeby mieć konto i przypisaną imprezę.

Szum z rozszerzeń przeglądarki (`chrome-extension://`, `content-script`) jest filtrowany — testuj w oknie incognito, jeśli raport jest zaszumiony.

---

## 1. Panel — Imprezy

Ścieżka bazowa: `/admin/events`

| # | Funkcja | Kroki | Oczekiwany wynik |
|---|---------|-------|------------------|
| 1.1 | Lista imprez | Otwórz listę, filtry, sortowanie | wiersze z płatnościami / statusem |
| 1.1a | Quick tabs listy | Zakładki: Dziś / Ten tydzień / Nadchodzące / Nierozliczone / Bez wypłaty pilota / Moje | filtruje listę bez DatePickerów |
| 1.1b | Deep-linke wiersza | Menu akcji wiersza → Program / Uczestnicy / Finanse / Pilot | bezpośrednie przejście (1 klik) |
| 1.1c | Szybki pilot (SlideOver) | Akcja „Szybki pilot” → przypisz + udostępnij → zapisz | pilot i `shared_with_pilot` zapisane |
| 1.1d | Status inline | Zmiana statusu w SelectColumn na liście | wywołuje `changeStatus()` (historia/snapshot) |
| 1.1e | Quick Actions huba | Podsumowanie imprezy — sticky pasek skrótów | Program, Finanse, Pilot itd. w 1 klik |
| 1.2 | Utworzenie z szablonu | Utwórz → wybierz szablon, daty, pilot | impreza z programem i cenami |
| 1.3 | Edycja podstawowa | Zmień nazwę, status, liczbę uczestników | zapis bez błędu |
| 1.4 | Pilot — data urodzenia, PESEL | Wybierz pilota, uzupełnij pola, zapisz | dane w profilu użytkownika (Users) |
| 1.5 | Program (`/program`) | Planer 24h, dodaj/edytuj punkt, drag & drop | zapis kolejności i godzin |
| 1.6 | Kalkulacja (`/calculation`) | Otwórz, przelicz | tabela kosztów, eksport PDF/Excel |
| 1.7 | Rezerwacje (relacja) | Dodaj rezerwację, status „Nie wymaga” | nie liczy się jako aktywna zaliczka |
| 1.8 | Rozliczenia (relacja) | Otwórz rozliczenie powiązane | koszty, płatności uczestników |
| 1.9 | Umowy / kontrakty (relacja) | Lista umów indywidualnych/grupowych | raport płatności, eksport CSV, profile wpłat (grupowa / indywidualna / grupowa-osobno / własna), numer operacyjny `{kod}U001` |
| 1.10 | PDF pakietu | Pobierz PDF (pilot / hotel / program) | plik PDF, poprawna treść | częściowo |
| 1.11 | Oferta Word | Eksport oferty Word z dashboardu imprezy | plik .docx / zip | 1 skipped auto |
| 1.12 | Zleceniodawcy wielokrotni | Wiele kontrahentów jako zleceniodawcy | zapis relacji | ✅ auto |
| 1.13 | Transport — wyszukiwanie kontrahenta | Impreza → Transport → wpisz nazwę firmy transportowej | domyślnie tylko typy przewoźnik/kierowca; checkbox „Szukaj we wszystkich kontrahentach” rozszerza listę | — |
| 1.14 | Kontakt ↔ firma (zamawiający) | Utwórz/edytuj imprezę → repeater zamawiających: nowy kontakt + nowa firma (lub para bez wcześniejszego powiązania) → zapisz | po zapisie kontakt widoczny w kontrahencie (zakładka Kontakty) i odwrotnie; brak duplikatów w pivot | — |
| 1.15 | Diety specjalne | Edycja imprezy → sekcja podstawowa → pole „Diety” (np. `1 x dieta bezglutenowa`) → zapisz → `/pilot` → podgląd trasy | sekcja „Diety” widoczna w teczce pilota z wpisanym tekstem; puste pole — sekcja ukryta | ✅ auto |

---

## 2. Panel — Szablony imprez

Ścieżka: `/admin/event-templates`

| # | Funkcja | Kroki | Oczekiwany wynik |
|---|---------|-------|------------------|
| 2.1 | Lista i edycja szablonu | CRUD podstawowych pól | zapis OK |
| 2.2 | Program szablonu (`/program`) | Drzewo punktów, dzieci, kolejność | bez błędów bezpieczeństwa | ✅ auto |
| 2.3 | Kalkulacja (`/calculation`) | Warianty qty, miejsca startowe | ceny PLN + obce, zaokrąglenia | ✅ auto |
| 2.4 | Transport (`/transport`) | Autobus, km, typ transportu | koszt transportu w kalkulacji | ✅ auto |
| 2.5 | Generowanie imprezy | Akcja „generuj imprezę” z szablonu | nowa impreza z danymi | ✅ auto |
| 2.6 | Warianty qty (relacja) | Dodaj wariant uczestników | przeliczenie cen |

Powiązane słowniki (Konfiguracja / Szablony): typy imprez, typy transportu, punkty programu szablonu, kategorie szablonów.

---

## 3. Panel — Finanse

| # | Moduł | URL (przykład) | Test |
|---|-------|----------------|------|
| 3.1 | Rozliczenia imprez | `/admin/event-settlements` | edycja, koszty programu, dokumenty; akcja „W imprezie” → settlement-summary; searchable select imprezy |
| 3.1a | Pulpit finansowy | `/admin/finance-overview` | KPI klikalne (rozliczenia, zaliczki pilota, KSeF, sterta); kwoty przez MoneyFormatter |
| 3.1b | Sterta płatności | `/admin/pending-payments-inbox` | filtry typu/płatnika zapamiętane w sesji; licznik; komunikat przy limicie źródeł |
| 3.2 | Rezerwacje | `/admin/reservations` | CRUD, filtry, analityka `/admin/reservations-analytics` |
| 3.3 | Umowy TFG (UFG) | `/admin/contracts` | tworzenie, edycja, anulowanie, aneks | ✅ auto |
| 3.4 | Logi feed TFG | `/admin/tfg-feed-logs` | podgląd, pobranie pliku feed |
| 3.5 | Słownik TFG | `/admin/tfg-dictionary-items` | CRUD pozycji słownika |
| 3.6 | Kursy walut (snapshot) | `/admin/currency-rate-snapshots` | podgląd historycznych kursów |

---

## 4. Panel — Kontakty

| # | Moduł | Test |
|---|-------|------|
| 4.1 | Kontrahenci `/admin/contractors` | CRUD, typy, **kontakty (relacja pivot)** — wcześniejszy błąd `contractor_contact` |
| 4.2 | Kontrahenci — PESEL, data urodzenia | pola w formularzu, zapis |
| 4.3 | Kontakty `/admin/contacts` | CRUD, powiązanie z kontrahentem |
| 4.4 | Płatnicy `/admin/payers` | CRUD |

---

## 5. Panel — Zadania i komunikacja

| # | Moduł | Test |
|---|-------|------|
| 5.1 | Zadania — lista | `/admin/tasks` |
| 5.2 | Zadania — Kanban | tablica Kanban, przenoszenie kart | API ✅ |
| 5.3 | Kalendarz zadań | widget na dashboardzie |
| 5.4 | Konwersacje | `/admin/conversations` lub `/admin/chat` |
| 5.5 | Czat | `/admin/chat` — wysłanie wiadomości |
| 5.6 | Toast nowych powiadomień | Panel admin otwarty >5 s; w drugiej sesji: nowe zadanie / impreza inquiry / wiadomość | toast Filament „Otrzymałeś nowe powiadomienia” (np. „2 nowe zadania · 1 nowa impreza”); liczniki w topbarze rosną; odświeżanie co ~2 min lub po powrocie do karty |
| 5.8 | Belka — nieodczytane + wnioski o fakturę | Zaloguj jako `ksiegowosc` / `admin`; w topbarze: klik w pozycję zadania / imprezy / wniosku o fakturę | pozycja znika z listy dropdown, licznik sekcji maleje o 1; mobile badge = suma `total_unread`; sekcja „Wnioski o fakturę” widoczna tylko dla ról finance; po modyfikacji zadania (update) pojawia się ponownie jako nieodczytane |
| 5.7 | Zadania — status, filtry, checklista | Szybkie filtry zakresu: **Moje zadania** (domyślnie) / **Moje zlecenia** / **Wszystkie** — lista, kanban, kalendarz, zadania przy imprezie; domyślnie ukryte zakończone, anulowane i **zarchiwizowane**; **podzadania widoczne na liście i kanbanie**; klik w tytuł otwiera modal pełnego widoku; powiadomienia topbar linkują do modala (`?editTask=`); domyślne sortowanie listy po **ostatniej aktywności** (edycja, komentarz, załącznik, podzadanie); alternatywnie sort po nagłówkach: termin, utworzono, priorytet |
| 5.9 | Zadania — impreza + Kanban | Zakładka zadań imprezy → **Kanban** → `/admin/tasks/board?event={id}`; powrót do zadań imprezy; tworzenie z prefill kontekstu |
| 5.10 | Zadania — archiwizacja | Bulk **Archiwizuj** dla statusów Zakończone/Zaakceptowane/Anulowane; filtr „Zarchiwizowane” (domyślnie ukryte) |
| 5.11 | Zadania — załączniki | Upload przy tworzeniu (dysk `public`); pobieranie przez `/admin/task-attachments/{id}/download` (autor, assignee, admin) |

---

## 6. Panel — Konfiguracja i narzędzia

| # | Moduł | Test |
|---|-------|------|
| 6.1 | Miejsca `/admin/places` | CRUD, slug, kraj |
| 6.2 | Odległości `/admin/place-distances` | pary miejsc, km |
| 6.3 | Waluty, podatki, marże | `/admin/currencies`, `/admin/taxes`, `/admin/markups` |
| 6.4 | Autobusy, pokoje hotelowe, ubezpieczenia | CRUD |
| 6.5 | Szablony umów | `/admin/contract-templates` |
| 6.6 | Statusy płatności / typy płatności | CRUD + uprawnienia Shield | ✅ auto |
| 6.7 | Media, tagi, opisy cen | CRUD |
| 6.8 | Import/Eksport | `/admin/import-export-panel` |
| 6.9 | Backup | `/admin/backup-manager` — utworzenie i pobranie archiwum | ✅ auto |
| 6.10 | Blog (admin) | `/admin/blog-posts` |
| 6.11 | Dokumenty / sekcje | `/admin/documents`, `/admin/document-sections` |
| 6.12 | Analityka wydatków | `/admin/expenses-analytics` |

---

## 7. Panel — Admin

| # | Moduł | Test |
|---|-------|------|
| 7.1 | Użytkownicy | CRUD, role, PESEL/data urodzenia pilota |
| 7.2 | Role i uprawnienia (Shield) | `/admin/roles` — ograniczenie dostępu do resource |
| 7.3 | Archiwum legacy | `/admin/legacy-events` — podgląd starych imprez |

---

## 8. Frontend publiczny

Bazowy URL: `http://127.0.0.1:8000/{regionSlug}` (region z bazy, np. `warszawa`).

| # | Ścieżka | Test |
|---|---------|------|
| 8.1 | `/` lub `/{region}` | strona główna, region |
| 8.2 | `/{region}/oferty` | katalog ofert, filtry miejsca startowego | ✅ auto |
| 8.3 | `/{region}/package/{slug}` | szczegóły oferty, ceny w walutach |
| 8.4 | Pretty URL `/{region}/{dni}/{id}/{slug}` | przekierowanie / wyświetlenie |
| 8.5 | Blog `/blog`, `/blog/{slug}` | lista i wpis |
| 8.6 | Dokumenty `/documents` | lista regulaminów |
| 8.7 | Kontakt, FAQ, ubezpieczenia | strony statyczne 200 |
| 8.8 | Podgląd zdjęć (preview) | miniatury na liście ofert | ✅ auto |
| 8.9 | Eksport Word z oferty | przycisk Word na stronie pakietu | manual |

---

## 9. Flow umowy publicznej

Token z panelu (umowa → link publiczny) lub z tabeli `contracts` / `event_agreements`.

| # | Krok | URL | Oczekiwany wynik |
|---|------|-----|------------------|
| 9.1 | Plan | `/umowa/{token}` | akceptacja planu | ✅ auto |
| 9.2 | Zgody | `/umowa/{token}/zgody` | checkboxy, zapis |
| 9.3 | Dane osobowe | `/umowa/{token}/dane` | regeneracja treści umowy |
| 9.4 | Podpis | POST zawrzyj | status podpisana |
| 9.5 | Płatność demo | `/umowa/{token}/platnosc` | synchronizacja z rozliczeniem | ✅ auto |
| 9.6 | Potwierdzenie | `/umowa/{token}/potwierdzenie` | podsumowanie |
| 9.7 | Umowa indywidualna — raport CSV | z panelu imprezy | plik CSV | ✅ auto |

---

## 10. API (Sanctum)

Bazowy URL: `http://127.0.0.1:8000/api/v1`

| # | Endpoint | Test | Auto |
|---|----------|------|------|
| 10.1 | `POST /auth/login` | token / sesja | ✅ |
| 10.2 | `GET /auth/me` | dane użytkownika | ✅ |
| 10.3 | `GET /events` | lista, filtry, paginacja | ✅ |
| 10.4 | `GET /events/{id}` | szczegóły | ✅ |
| 10.5 | `GET /events/{id}/calculation` | kalkulacja JSON | ✅ |
| 10.6 | `POST /events/{id}/recalculate-price` | przeliczenie | ✅ |
| 10.7 | `POST /events/{id}/program-points/reorder` | zmiana kolejności | ✅ |
| 10.8 | `GET /tasks/board` | Kanban JSON | ✅ |
| 10.9 | `POST /tasks/{id}/move` | przeniesienie zadania | ✅ |
| 10.10 | `GET /notifications/counts` | liczniki | ✅ |

Przykład (po `php artisan serve`):

```bash
curl -s -X POST http://127.0.0.1:8000/api/v1/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@example.com","password":"haslo"}'
```

---

## 11. TFG / UFG (integracja)

| # | Test | Kroki | Auto |
|---|------|-------|------|
| 11.1 | Instalacja modułu | `make ufg-install` na bazie legacy | — |
| 11.2 | Migracja umów | `contracts:migrate-from-event-agreements` | ✅ |
| 11.3 | Walidacja payload TFG | formularz umowy — błędy ICAO / wymagane pola | ✅ |
| 11.4 | Mock feed | submit → poll → status „zawarta” | ✅ |
| 11.5 | Korekta | blokada bez „zawarta”, sukces po zawarciu | ✅ |

---

## 12. Operacje serwerowe

| # | Test | Komenda |
|---|------|---------|
| 12.1 | Weryfikacja CI lokalnie | `composer verify` |
| 12.2 | Paczka wdrożeniowa | `make deploy-package` |
| 12.3 | Backup przywracalny | Backup Manager → restore (środowisko testowe) | ✅ auto |
| 12.4 | Przycinanie starych backupów | `php artisan backup:prune` | ✅ auto |

---

## 13. Regresje znane (obserwuj przy testach)

| Obszar | Objaw | Status |
|--------|-------|--------|
| Kontrahenci — kontakty | `contractor_contact` missing | naprawione migracją + `make setup-fresh` |
| Raport umów indywidualnych | kolejność wierszy | naprawione — sort po `participant_name` |
| Eksport Word oferty | redirect zamiast ZIP w CI | test skipped — wymaga środowiska z LibreOffice/PHPWord |
| Rozszerzenia Chrome | błędy `content-script.js` | nie dotyczy aplikacji |

---

## 11. Portal pilota (`/pilot`)

| # | Scenariusz | Kroki | Oczekiwany wynik |
|---|------------|-------|------------------|
| 11.1 | Logowanie pilota | Wejdź na `/pilot/login`, zaloguj użytkownika z rolą `pilot` | panel pilota, brak dostępu do `/admin` |
| 11.2 | Kalendarz wycieczek | Pulpit → kalendarz / lista „Moje wycieczki” | widoczne tylko imprezy z `assigned_to` = pilot |
| 11.3 | Teczka imprezy | Otwórz wycieczkę → „Teczka PDF” / „Pakiet pilota PDF” | pobranie PDF, dane programu i uwagi |
| 11.4 | Rozliczenie (panel) | Rozliczenie → uwagi, liczba osób, licznik start/koniec | zapis, status opcjonalnie „Pilot rozliczył” |
| 11.5 | Lista wydatków pilota | Otwórz rozliczenie imprezy z programem → „Odśwież z wycieczki” | widoczne tylko pozycje `paid_by=pilot` (z programu lub ręczne); pozycje biura ukryte |
| 11.6 | Edycja wydatku | „Edytuj kwotę” → wpisz kwotę faktyczną → „Zapisz kwotę” | kwota zapisana; przy pozycji z programu widać „Brak kwoty faktycznej” dopóki pilot nie wpisze |
| 11.7 | Skan do wydatku | Przy pozycji wydatku → „Zrób zdjęcie” (telefon) lub „Wybierz plik” | aparat otwiera się na mobile; dokument z `linked_cost_ids` w adminie |
| 11.8 | Wydatek nieprzewidziany | Dodaj ręczny wydatek | `source_type=manual`, można usunąć |
| 11.9 | Rozliczenie gotówki | Biuro: 2000 PLN pilotowi; pozycje: plan ~1832, faktycznie 1726 | „Do zwrotu” = 2000 − 1726 − zwrócone (np. 274) |
| 11.10 | Widok mobilny | `/pilot/trip/{id}/settle` | ta sama lista wydatków i gotówka co w panelu |
| 11.11 | Zamknięcie przez biuro | W adminie ustaw rozliczenie na `closed` | pilot nie może edytować raportu |

**Szybka konfiguracja (zalecane):**

```bash
make pilot-demo
composer dev   # w osobnym terminalu
```

| Konto | E-mail | Hasło |
|-------|--------|-------|
| Demo (make pilot-demo) | `pilot@test.local` | `pilot123` |
| Legacy (db:seed) | `piotr.zielinski@example.com` | `zielony2024` |

Komenda `php artisan pilot:setup-demo --migrate` przypisuje pilota do 3 imprez i zakłada rozliczenia. Bez `--migrate` tylko konfiguruje użytkownika (jeśli migracje już są).

---

## 12. Faktury KSeF (`/admin`)

Grupa menu **Faktury**. Konto: `admin` lub rola `ksiegowosc` (uprawnienia `view_vendor_invoice`, `import_vendor_invoice`).

| # | Funkcja | Kroki | Oczekiwany wynik |
|---|---------|-------|------------------|
| 12.1 | Import jednorazowy | `/admin/vendor-invoice-import-page` → wgraj jednocześnie `CSV.csv`, `xml.xml` i zbiorczy PDF | ~17 faktur + PDF dopięte do rekordów po KSeF |
| 12.2 | Import ponowny | ten sam zestaw plików drugi raz | idempotencja po numerze KSeF (brak duplikatów) |
| 12.3 | Rejestr | `/admin/vendor-invoices` | filtry płatność/akceptacja/dopasowanie, kolumny brutto/termin/impreza |
| 12.4 | Stos | `/admin/vendor-invoice-inbox-page` | tylko niedopasowane; akcja „Przypisz” → impreza + punkt programu |
| 12.5 | Raporty | `/admin/vendor-invoice-reports-page` | zakładki: do zapłaty, przeterminowane, zaakceptowane nieopłacone, eksport CSV |
| 12.6 | Kontrahent | edycja kontrahenta → zakładka Faktury | faktury po `contractor_id` lub NIP wystawcy |
| 12.7 | Impreza | edycja imprezy → Faktury kosztowe | lista faktur przypisanych do imprezy |
| 12.8 | Akceptacja + rozliczenie | edycja faktury → zaakceptuj + checkbox „Dodaj do rozliczenia” | dokument w rozliczeniu imprezy (`EventSettlementDocument`) |
| 12.9 | Zbiorczy PDF (faza 2) | import `pliki/ksef/Faktury pdf w jednym pliku.pdf` po CSV | pliki PDF powiązane z rekordami po KSeF |

Testy automatyczne: `php artisan test --filter=VendorInvoiceModuleTest`

---

## Szybka ścieżka regresji (30 min)

Jeśli nie masz czasu na pełną checklistę:

1. `make test`
2. Logowanie admin + dashboard
3. Edycja imprezy → program → kalkulacja → PDF
4. Kontrahent z relacją kontaktów (np. `/admin/contractors/382/edit`)
5. Frontend: `/{region}/oferty` + jedna oferta
6. Flow umowy: token testowy do płatności demo
7. Jedna umowa TFG w `/admin/contracts`

---

## 14. Plan noclegów (impreza + szablon)

| # | Scenariusz | Kroki | Oczekiwany wynik |
|---|------------|-------|------------------|
| 14.1 | Snapshot przy tworzeniu | Utwórz imprezę ze szablonu z noclegami | `event_hotel_stays` + linie pokoi; notatki nocy ze szablonu |
| 14.2 | Plan imprezy | `/admin/events/{id}/hotel-planning` | oś czasu noclegów, hotel, pokoje, kwoty, osoby |
| 14.3 | Kopiowanie | „Ten sam hotel na wszystkie noce” / „Kopiuj pokoje” | struktura i hotel skopiowane |
| 14.4 | Osoby w pokojach | przypisz z umów + dodaj ręcznie | ta sama osoba nie może być w 2 pokojach tej nocy |
| 14.5 | Oferta w cenie | pole „Co hotel oferuje w cenie” | widoczne w PDF hotelu |
| 14.6 | Szablon | `/admin/event-templates/{id}/hotel-planning` | kopiuj na wszystkie / następną, notatki per noc |
| 14.7 | Wyszukiwanie hotelu | Plan noclegów → wyszukaj kontrahenta | domyślnie tylko typ hotel; checkbox „Szukaj we wszystkich kontrahentach” dla źle przypisanego typu | — |

Testy automatyczne: `php artisan test --filter=EventHotelPlanTest`

---

## 15. UI workflow (admin + pilot)

Checklist po redesignie nawigacji workspace i responsywności.

| # | Obszar | Kroki | Oczekiwany wynik |
|---|--------|-------|------------------|
| 15.1 | Sub-nav szablonu | Edycja szablonu → zakładki: Dane, Program, Noclegi, Transport, Kalkulacja, Warianty | poziome zakładki, pasek kontekstu z CTA „Generuj imprezę” |
| 15.2 | Sub-nav imprezy | Edycja imprezy → zakładki workspace | brak zakładek relation manager pod formularzem; Rezerwacje/Umowy/Dokumenty/Rozliczenie jako osobne zakładki |
| 15.3 | Nagłówek imprezy | Edycja → „Dokumenty PDF” (grupa) | mniej przycisków w rzędzie; oferta DOCX + rozliczenie widoczne |
| 15.4 | Lista imprez mobile | wąskie okno (~390px) | kolumny: kod, impreza, status; bez poziomego chaosu |
| 15.5 | Lista imprez laptop | 1280×800 | klient, uczestnicy, finanse widoczne; marża ukryta domyślnie |
| 15.6 | Sub-nav rozliczenia | Edycja rozliczenia | Podsumowanie, Koszty programu, Koszty, Płatności, Gotówka pilota, Dokumenty |
| 15.7 | Menu boczne | panel admin | grupy: Operacje, Finanse, Kontakty, Ustawienia, System |
| 15.8 | Pulpit | dashboard admin | widget „Kontynuuj pracę” + KPI finansowe |
| 15.9 | Portal pilota | `/pilot` | KPI, filtry listy wycieczek, sub-nav Informacje / Rozliczenie / Dokumenty |
| 15.10 | iPad | szer. ~768px | sub-nav przewijany w poziomie; przyciski min. 44px |
| 15.11 | Podgląd UI | System → Podgląd UI (`/admin/design-system-preview-page`) | logo SVG, paleta kolorów, przyciski, empty states |

---

## 16. Finanse operacyjne, KSeF, program, pilot (2026-07)

| # | Obszar | Kroki | Oczekiwany wynik |
|---|--------|-------|------------------|
| 16.1 | KSeF → punkt programu | Inbox KSeF → przypisz fakturę do imprezy i punktu | `paid_price` i stos płatności punktu się aktualizują; badge „F” spójny |
| 16.2 | Filtr płatnika (program) | Lista punktów programu → filtr Płatnik | Biuro / Pilot / Wszyscy |
| 16.3 | Zaliczka pilota | Impreza → Pilot → plan + zatwierdzenie wypłaty | kwota, waluta, komentarz zapisane; widoczne w panelu pilota (zakładka Zaliczka) |
| 16.4 | Udostępnienie imprezy | Impreza → Pilot → **„Podgląd panelu pilota”** (dodaje `?preview=1`) | admin/biuro widzi teczkę w `/pilot/pilot-events/{id}`; bez `preview=1` konto admin+pilot widzi tylko swoje imprezy |
| 16.5 | Wymiana walut | Rozliczenie → Gotówka pilota → Wymiany walut | wpis FX nie jako koszt; saldo w reconciliation |
| 16.6 | Program — daty | Planer 24h → dodaj punkt przez północ | data start/koniec + sloty HH:mm; walidacja godzin |
| 16.7 | Program — kolejność | Lista punktów → przeciągnij wiersz za uchwyt | zmiana `order`; sety wizualnie pogrupowane |
| 16.7b | Program — bloki/sety | Program → zakładka „Bloki / sety” | dni, sety z podpunktami, badge Set · N |
| 16.7c | Finanse setu (admin) | Set z 2 podpunktami w PLN + EUR (bez przeliczenia) → lista programu | rodzic setu: kolumna Ceny pokazuje sumę „100 PLN + 50 EUR” z etykietą Σ set; Rozliczenie i terminy — zbiorczo |
| 16.7d | Finanse setu (pilot) | Portal pilota → program wycieczki z setem (biuro + pilot) | jeden kompaktowy blok finansów na rodzicu; podpunkty z adnotacją „wchodzi w set”, bez duplikatu kwot |
| 16.7e | Pilot płaci (program) | Admin → punkt programu → rozliczenie: Płaci = **Pilot** (plan lub zaliczka) → `/pilot/program/{id}` | badge „👤 Pilot płaci” + niebieski blok: plan / zaliczka / termin; przy setach także na podpunkcie pilota |
| 16.7f | Finanse setów (suma + rozbicie) | Set z podpunktami (w tym poza programem): pilot + biuro → Program pilota, Teczka (Informacje), PDF pilota i teczki | karta setu: „Łącznie do zapłacenia” (tylko pilot) + lista podpunktów; biuro: „opłacone przez biuro” / „płaci biuro”; set poza programem w sekcji „Sety poza programem” |
| 16.7g | Program — odświeżanie kwot w liście | Impreza → Program → Lista → punkt z rozliczeniem → Plan / Zaliczka / Wpłaty: zmień kwotę i zapisz (także od razu Zapisz, bez Tab) | kolumna „Ceny & Zaliczka” (Planowana, Zapłacona, zaliczka) aktualizuje się bez F5; przy setach wiersz Σ set pokazuje nową sumę po zmianie podpunktu |
| 16.7h | Program — wiele zaliczek | Impreza → Program → punkt → Zaliczka → Dodaj zaliczkę (2 wpisy: kwota, waluta, płatne do; jedna bez „Zapłacone dnia”) → Zapisz | w kolumnie: „Zaliczki” + suma; niezapłacona z terminem → zadanie w inboxie; po uzupełnieniu „Zapłacone dnia” zadanie znika, „do dop.” = plan − zapłacono |
| 16.8 | Kalendarz operacyjny | `/admin/operations-calendar` | filtry typów: imprezy, KSeF, płatności, zaliczki, transport, hotele |
| 16.9 | Sterta płatności | `/admin/pending-payments-inbox` | rozliczenia + raty kontraktów TFG + umowy; filtry typu; kalendarz; persist filtrów w sesji; ostrzeżenie limitu |
| 16.10 | Semafor rozliczenia | Impreza → Finanse → Podsumowanie rozliczenia | karty zielony/niebieski/pomarańczowy/czerwony; biuro vs pilot (plan/wpłacono/brakuje); tabela Kontrola planu; pozycje do korekty linkują do kosztów |
| 16.11 | Semafor w kosztach | Impreza → Koszty rozliczenia | kolumna Semafor + filtr; klik w pozycję z czerwonym/pomarańczowym → korekta wpłat |
| 16.12 | Raport rozliczenia | Podsumowanie rozliczenia → Eksport raportu | plik Excel: podsumowanie, koszty, wpłaty uczestników |
| 16.13 | Wpłaty uczestników — salda | `/admin/participant-payments` + portal klienta | kolumny Brakuje/Semafor/Następna rata; filtry niedopłat i po terminie |
| 16.14 | Ledger wpłat w rozliczeniu | Impreza → Uczestnicy → Wpłaty | 4 kolumny (uczestnik, historia wpłat, pozostało, akcje); **+** dodaje wpłatę; X usuwa pojedynczą wpłatę; **Wyślij przypomnienie** gdy pozostało > 0; import bankowy tworzy wpis `bank_import` |
| 16.15 | Sub-nav Uczestnicy | Impreza → Uczestnicy | podzakładki: Lista, Wpłaty, Rezygnacje, Portal klienta; główny pasek bez osobnych Rezygnacji/Portalu; Finanse bez Wpłat uczestników; banner „Sekcja dotyczy wyłącznie uczestników imprezy …”; link **Dane imprezy** w kontekście workflow |
| 16.16 | Import CSV w kontekście imprezy | Impreza → Uczestnicy → Wpłaty | panel „Import wpłat bankowych (Millennium)” nad ledgerem; po wczytaniu CSV widoczne tylko wpływy tej imprezy; komunikat o ukrytych wpłatach innych imprez; **Zaksięguj zaznaczone** odświeża ledger |
| 16.17 | Powrót do danych imprezy | Dowolna podzakładka Uczestnicy | w pasku kontekstu workflow link **Dane imprezy** → edycja imprezy; tytuł strony zawiera kod imprezy |
| 16.10 | Hotel — plan/kalk/zapłacona | Impreza → Noclegi | kolumny planned / calculated / paid + filtr zakresu dat |
| 16.11 | Mail udostępnienia | Pilot → „Udostępnij pilotowi” (pilot z e-mailem) | mail z kodem imprezy i linkiem `/pilot/login` |
| 16.12 | Frontend Vite | po `npm run build` | `public/vite-dist/manifest.json`; kalendarze bez CDN (prod) |

---

## 17. Miejsca prowadzenia kontrahenta (2026-07)

| # | Obszar | Kroki | Oczekiwany wynik |
|---|--------|-------|------------------|
| 17.1 | Kontrahent — flaga | Kontrahenci → edycja → „Wiele miejsc prowadzenia działalności” = tak → **Zapisz** | pod adresem rozliczeniowym pojawia się repeater „Dodaj oddział” (nie osobna zakładka) |
| 17.2 | Dwa oddziały | Dodaj 2 miejsca (np. Zakopane + Sopot) z różnymi adresami i telefonami | oba widoczne w tabeli; jedno może być „Domyślne” |
| 17.3 | Program imprezy | Punkt programu → wykonawca sieci hotelowej → wybierz oddział | zapis `contractor_location_id`; w tabeli pod nazwą — adres oddziału |
| 17.4 | Plan noclegów | Impreza → Noclegi → hotel sieciowy → wybierz oddział | auto-zapis noclegu z lokalizacją |
| 17.5 | Pilot — program | Portal pilota → program wycieczki | „Adres podjazdu” z miasta oddziału, nie siedziby |
| 17.6 | Pilot — noclegi | Portal pilota → Plan hotelu | nazwa oddziału + adres operacyjny |
| 17.7 | PDF kierowca | Admin → Dokumenty PDF → Pakiet kierowcy | hotel z adresem oddziału |
| 17.8 | Kontrahent bez flagi | Firma jedno-adresowa (flaga wyłączona) | brak selecta lokalizacji; zachowanie jak dotychczas |

Testy automatyczne: `composer test -- --filter='ProgramPointListFinance|ProgramPointSetFinance|PilotProgramPointFinance'`

---

## 18. Rezerwacja punktu programu — workflow (2026-07)

| # | Obszar | Kroki | Oczekiwany wynik |
|---|--------|-------|------------------|
| 18.1 | Modal z programu | Impreza → Program → punkt → Więcej → „Rezerwacja” | formularz bez pola kontrahenta (z punktu); status + terminy |
| 18.2 | Terminy | Uzupełnij: Potwierdzić do, Potwierdzono, Zaliczka do, Zaliczka zapłacona | daty zapisane; w tabeli programu naklejki statusu i zaliczki |
| 18.3 | Załącznik PDF | Wgraj plik potwierdzenia w modalu rezerwacji | plik w `reservation-attachments`; widoczny przy pełnej edycji |
| 18.4 | Notatki biura | Wpisz notatkę w polu „Notatki biura” | naklejka notatki w planerze 24h (podgląd); widoczne w programie |
| 18.5 | Historia | Zmień status rezerwacji i zapisz ponownie | wpis w „Historia zmian ustaleń” z datą i użytkownikiem |
| 18.6 | Sync zaliczki | Ustaw „Zaliczka zapłacona” + kwotę rezerwacji | koszt rozliczenia: `advance_paid`, termin zaliczki z rezerwacji |
| 18.7 | Edycja z tabeli | Więcej → „Edytuj rezerwację” | ten sam uproszczony formularz; kontrahent tylko do odczytu |
| 18.8 | Lista globalna | `/admin/reservations` | kolumna Terminy pokazuje nowe pola workflow |

Testy automatyczne: `composer test -- --filter='ReservationWorkflow|ReservationAdvanceDueDate'`

---

## 19. Portal klienta (`/portal`)

| # | Scenariusz | Kroki | Oczekiwany wynik |
|---|------------|-------|------------------|
| 19.1 | Zaproszenie z biura | Impreza → Portal klienta → Zaproś uczestnika / opiekuna | rekord w tabeli dostępów, e-mail z loginem lub powiadomieniem |
| 19.2 | Auto po umowie | Przejdź `/umowa/{token}` do podpisu i płatności demo | e-mail z dostępem do `/portal/login`, link na stronie sukcesu |
| 19.3 | Izolacja uczestnika | Zaloguj uczestnika A i B na tej samej imprezie | każdy widzi tylko swoją umowę i wpłatę; brak listy innych uczestników |
| 19.4 | Opiekun — wpłaty grupy | Zaloguj opiekuna → Wpłaty grupy | tabela wpłat **każdego uczestnika** (nazwiska, referencje, kwoty) |
| 19.5 | Wniosek o fakturę — opiekun | Opiekun → Wniosek o fakturę → wyślij | wpis w historii; widoczny w adminie |
| 19.5b | Wniosek o fakturę — uczestnik | Uczestnik → Wniosek o fakturę → wyślij | wniosek powiązany z jego umową; bez dostępu do wpłat innych |
| 19.6 | Archiwum | Impreza zakończona > 90 dni temu | podstawowe info OK; program/umowa niedostępne (403) |
| 19.7 | Podgląd biura | Admin → Portal klienta → Podgląd `?preview=1` | widok wszystkich imprez bez osobnego konta klienta |

Testy automatyczne: `composer test -- --filter=Client`

---

## 20. Wymagania operacyjne (plan 7 sekcji)

Status: **zautomatyzowane testy** `OperationalRequirementsSection20Test` + regresje modułowe; **console-audit 173/173**.

| # | Obszar | Kroki | Oczekiwany wynik | Auto |
|---|--------|-------|------------------|------|
| 20.1 | Wnioski o fakturę | Finanse → Wnioski o fakturę; topbar → Pełna lista | inbox z akcjami Zrealizowany/Odrzuć; topbar linkuje do inbox | `ClientInvoiceRequestAdminTest` |
| 20.2 | Topbar zadań | Utwórz zadanie; odśwież panel | licznik spójny po ~60s i po zapisie (event `refresh-notifications`) | `NotificationServiceTopbarTest` |
| 20.3 | Sort list operacyjnych | Imprezy / Umowy / Rezerwacje / Kontrahenci | domyślnie `updated_at` malejąco | `OperationalListSortTest` |
| 20.4 | Ledger wpłat | Impreza → Uczestnicy → Zapłacono → + wpłata | pola Płatnik, Opis przelewu, Rodzaj wpłaty; saldo rozliczenia się aktualizuje | `ParticipantPaymentLedgerServiceTest` |
| 20.5 | Program — płatnik | Impreza → Program → kolumna Płatnik | szybka zmiana Biuro/Pilot bez modala | ręcznie / program UI |
| 20.6 | Rezerwacje imprezy | Impreza → Rezerwacje → Dodaj rezerwację | pełny formularz w modalu (bez redirectu) | ręcznie |
| 20.7 | WWW per impreza | Impreza → Strona WWW → zapisz; front oferty szablonu | dodatkowy blok informacji gdy impreza ma `www_extra_info` | `OperationalRequirementsSection20Test` |
| 20.8 | ACL szablony | Rola `programista` vs `biuro` | programista: tylko program szablonu; biuro: imprezy bez edycji globalnego szablonu | `EventTemplateAccessTest` |
| 20.9 | Umowy — płeć/dieta | Impreza → Umowy → edycja / szybkie tworzenie | sekcja Uczestnik: płeć, dokument, dieta + 20 PLN/dzień | ręcznie |
| 20.10 | Ubezpieczenia dzienne | Impreza → Ubezpieczenia → dodaj polisę dnia | koszt w rozliczeniu (`insurance_day`) odświeża się po zapisie | `EventDayInsuranceSettlementSyncTest` |
| 20.11 | Zbiórka autokar | Pilot / rozliczenie → Zbiórki w autokarze | kwota za osobę × liczba osób = suma przed zapisem | `EventBusCollectionTest` |
| 20.12 | PDF kierowcy — kolory trasy | Dokumenty PDF → Pakiet kierowcy | odjazd niebieski, hotel zielony, powrót pomarańczowy (wielodniowa), inne miejsce powrotu fioletowe | `EventFolderPdfServiceTest` |
| 20.13 | Program — sety i uwagi | Impreza → Program → dodaj set z szablonu | set rozwija się po dodaniu; godziny propagują się na podpunkty; znaczniki Biuro/Pilot przy nazwie | `EventProgramPointCreatorTest`, `TemplateSetTimingDefaultsTest` |
| 20.14 | Cena ręczna | Impreza → edycja → Cena za osobę | toggle „Zablokuj auto-przeliczanie”; cena nie ginie po przeliczeniu kalkulacji | `EventManualPriceAndResignationTest` |

### Backlog domknięty w tej iteracji

| Temat | Status |
|-------|--------|
| Korespondencja z hotelem | MVP: `EventHotelCorrespondencePanel` (log ręczny) — IMAP poza zakresem |
| Żółte karteczki | `StickyNotesStack` — stos notatek bez wyboru kategorii w UI |
| Godziny setów per typ wycieczki | pola szablonu `set_default_child_count` + `set_default_slot_minutes` |
| Legacy formularze kosztów | `ProgramPointsCostsRelationManager` → `ParticipantPricingFields` |
| Paginacja / push powiadomień | `NotificationsInboxPage`: paginacja + `wire:poll.60s` + `refresh-notifications` |

Testy automatyczne: `composer test -- --filter='OperationalRequirementsSection20|TemplateSetTimingDefaults|NotificationsInboxPage|EventProgramPointCreator|EventBusCollection|EventFolderPdf'`

---

## Powiązane dokumenty

- [DEV.md](DEV.md) — uruchomienie środowiska
- [PL/README.md](PL/README.md) — opis komponentów
