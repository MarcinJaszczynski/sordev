# Prompt: generowanie 4 wariantów PDF (pilot / kierowca / hotel / teczka imprezy)

Kontekst: istniejąca aplikacja Laravel do zarządzania wycieczkami (model `Trip`
z powiązanymi `TripDay`, `ItineraryItem`, `RoomAssignment`, `ExpenseItem`,
`PilotNote`, `TripFile` i encjami słownikowymi `Pilot`, `Driver`, `Carrier`,
`Client`, `Contractor`, `Accommodation`). Zadanie: dodać generowanie czterech
odrębnych, estetycznych dokumentów PDF z jednej wycieczki, każdy z innym
zakresem danych, przeznaczony dla innego odbiorcy.

## Zakres danych per dokument

**1. Pakiet pilota** — pełne dane operacyjne dla osoby prowadzącej grupę:
nagłówek wycieczki, pełna trasa i program dzień po dniu wraz z danymi
kontaktowymi każdego kontrahenta (przewodnik, atrakcja), struktura pokoi,
notatki dla pilota, liczba uczestników i wymagania dietetyczne, sekcja
rozliczenia z kwotami planowanymi i pustymi polami na kwoty rzeczywiste do
uzupełnienia w terenie, lista załączników, na końcu regulamin obowiązków
pilota.

**2. Pakiet kierowcy** — tylko to, co potrzebne do jazdy: kod wycieczki i
termin, trasa dzień po dniu z miejscami i orientacyjnymi godzinami przejazdów
(bez opisów atrakcji), miejsce/godzina podstawienia i powrotu, adresy miejsc
noclegowych/parkingów, liczba uczestników (do weryfikacji miejsc w
autokarze), dane kontaktowe pilota i biura, pole na stan licznika
początkowy/końcowy. Bez rozliczenia, struktury pokoi, danych klienta i
załączników.

**3. Pakiet hotelu** — rooming lista: nazwa grupy/zamawiającego i termin
pobytu, liczba uczestników (total + opiekunowie), pełna struktura pokoi,
wymagania dietetyczne, skrócony harmonogram przyjazdu/wyjazdu i posiłków
(bez pełnego programu zwiedzania), dane kontaktowe pilota do koordynacji na
miejscu. Bez rozliczenia biura, danych kontrahentów zewnętrznych i
regulaminu pilota.

**4. Teczka imprezy** — pełny dokument wewnętrzny/archiwalny biura,
zawierający wszystkie sekcje z pakietu pilota plus kompletne rozliczenie z
numerami faktur i sumami w oryginalnych walutach — to jest dokument źródłowy,
z którego renderowane są pozostałe trzy warianty.

## Prompt do wklejenia

```
W istniejącej aplikacji Laravel dodaj generowanie czterech wariantów PDF dla
modelu Trip: pilot, driver, hotel, event_folder.

ARCHITEKTURA
- Stwórz klasę `App\Services\TripPdfExporter` z metodą
  `generate(Trip $trip, string $variant): \Illuminate\Http\Response`, gdzie
  $variant to jedno z: pilot, driver, hotel, event_folder.
- Dla każdego wariantu przygotuj osobny widok Blade w
  resources/views/pdf/trips/{variant}.blade.php, każdy dziedziczący ze
  wspólnego layoutu resources/views/pdf/layout.blade.php (nagłówek z logo
  biura i danymi z pliku konfiguracyjnego, stopka z numeracją stron i
  wygenerowaną datą).
- Każdy widok korzysta wyłącznie z danych dozwolonych dla danego wariantu
  (patrz sekcja "Zakres danych" poniżej) — nie przekazuj do widoku danych,
  których wariant nie powinien pokazywać (np. rozliczenia do widoku driver).
- Użyj Browsershot (spatie/browsershot) jeśli dostępny Node/Chromium w
  środowisku, w przeciwnym razie barryvdh/laravel-dompdf. Layout ma być
  czysty: jedna kolumna, wyraźne nagłówki sekcji, tabele z naprzemiennym tłem
  wierszy, akcentowy kolor firmowy tylko w nagłówkach sekcji i liniach
  tabel — bez gradientów i cieni (PDF ma być czytelny wydrukowany
  czarno-biało).

ZAKRES DANYCH PER WARIANT
- pilot: dane wycieczki (kod, nazwa, termin), pełna trasa i program dzień po
  dniu z kontaktami kontrahentów, struktura pokoi, notatki dla pilota, liczba
  uczestników i uwagi dietetyczne, tabela rozliczenia z kolumnami: pozycja,
  cena jedn., ilość, suma planowana, [puste pole do wpisania ręcznie: suma
  rzeczywista], lista załączników (nazwa + opis), na końcu treść z modelu
  PilotDuty (aktywny regulamin).
- driver: kod wycieczki, termin, trasa dzień po dniu (tylko: data, miejsce
  wyjazdu, miejsce docelowe, orientacyjna godzina — bez opisu atrakcji),
  miejsce i godzina podstawienia oraz powrotu, adresy noclegów/parkingów,
  liczba uczestników total, dane kontaktowe pilota i biura, dwa puste pola:
  "Stan licznika: początek ____ / koniec ____".
- hotel: nazwa zamawiającego, termin pobytu, liczba uczestników (total +
  opiekunowie), pełna tabela struktury pokoi (numer, typ, notatki), sekcja
  wymagań dietetycznych, skrócony harmonogram (dzień przyjazdu z godziną,
  dzień wyjazdu z godziną, godziny posiłków jeśli ustalone), telefon
  kontaktowy do pilota.
- event_folder: wszystkie dane bez ograniczeń — to pełny odpowiednik
  obecnej "teczki pilota": nagłówek, pełny program, struktura pokoi,
  notatki, kompletne rozliczenie z numerami faktur i sumami per waluta
  (PLN/CZK/inne) plus przeliczenie łączne wg kursu z trip.exchange_rates,
  lista załączników, regulamin pilota.

KONTROLER / AKCJE
- Dodaj route `GET /trips/{trip}/pdf/{variant}` z route model binding i
  walidacją $variant przez enum lub Rule::in(['pilot','driver','hotel',
  'event_folder']).
- Jeśli panel admina to Filament: dodaj na TripResource cztery akcje
  (Action::make) z ikonami i etykietami "Pobierz pakiet pilota" / "...
  kierowcy" / "... hotelu" / "Pobierz teczkę imprezy", każda wywołująca
  odpowiedni wariant i zwracająca download response.

NAZEWNICTWO PLIKÓW
- Generowany plik nazwij wg wzoru: {kod_wycieczki}_{wariant}.pdf, np.
  "20230622-1051_pilot.pdf" (zamień znaki niedozwolone w nazwie pliku).

TESTY
- Feature test dla każdego z 4 wariantów: sprawdź status 200, content-type
  application/pdf, oraz że dla wariantu driver treść odpowiedzi NIE zawiera
  żadnej kwoty z tabeli rozliczenia (np. przez ekstrakcję tekstu z PDF i
  assertStringNotContainsString na przykładowej kwocie testowej), a dla
  event_folder wszystkie sekcje są obecne.

Dostarcz kompletny, gotowy do wdrożenia kod: serwis, cztery widoki Blade,
wspólny layout, routes, testy.
```

## Uwaga dot. „ładnego” wyglądu

Dla estetyki PDF-a (niezależnie od wariantu) warto trzymać się kilku zasad
w layoucie Blade:
- jeden krój pisma, dwie grubości (regularna + pogrubiona nagłówki sekcji),
- kolor firmowy tylko jako akcent (linia pod nagłówkiem, tło nagłówka tabeli),
- tabele z cienkimi liniami poziomymi zamiast pełnej siatki,
- stały margines i nagłówek/stopka z numeracją stron na każdej stronie,
- sekcje oddzielone wyraźnym odstępem, a nie tylko linią.
