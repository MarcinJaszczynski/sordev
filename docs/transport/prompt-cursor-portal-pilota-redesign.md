# Prompt dla Cursor — przeprojektowanie "Portalu pilota / podglądu biura"

Skopiuj poniższy tekst i wklej jako polecenie w Cursorze (najlepiej w trybie Agent, z otwartym repo). Dołącz też plik `mockup-reference-portal-pilota.html` do repo oraz 3 zrzuty ekranu obecnego widoku (Informacje, Program, Hotele).

---

## PROMPT

W repo, w katalogu głównym (lub tam gdzie wygodniej), znajduje się plik **`mockup-reference-portal-pilota.html`** — otwórz go w przeglądarce i przeanalizuj jako wzorzec wizualny. To statyczna makieta (czysty HTML/CSS, bez PHP/Blade/Livewire) pokazująca DOKŁADNY układ, który ma mieć przebudowany widok. **WAŻNE: ta makieta jest napisana mobile-first** — podstawowy layout (bez media query) to wąska kolumna jak na telefonie (max-width 420px), a reguła `@media (min-width: 640px)` dopiero rozszerza układ na desktop (np. do siatki dwukolumnowej). Zaimplementuj komponenty w tej samej kolejności: najpierw upewnij się, że wygląda dobrze na wąskim ekranie, potem dodaj układ szerszy dla desktopu — nie odwrotnie.

To NIE jest panel administracyjny Filament — to widok **portalu pilota / trybu podglądu biura**, czyli strona, z której korzysta pilot wycieczki (często z telefonu, w trasie) oraz biuro w trybie podglądu. Dlatego styl ma być bardziej "konsumencki" niż w panelu admina: karty ze zdjęciem wycieczki, zaokrąglone pigułki zamiast kwadratowych przycisków w pasku nawigacji, awatary z inicjałami przy kontaktach — jak w dobrej aplikacji mobilnej, a nie jak w backoffisie.

Pracujemy w aplikacji Laravel, prawdopodobnie z widokami Blade i/lub komponentami Livewire (nie Filament) dla tej części — potwierdź to po znalezieniu plików. Chcę przeprojektować ten widok bez usuwania jakiejkolwiek istniejącej funkcjonalności ani danych — w tym trybu "tylko odczyt" dla podglądu biura, informacji o statusie ("Zakończ podgląd", "Wybierz Podgląd jako ten pilot"), oraz wszystkich danych pokazywanych w każdej zakładce.

### Krok 1 — znajdź odpowiednie pliki
Zanim zaczniesz zmieniać kod, znajdź i pokaż mi listę plików odpowiedzialnych za ten widok. Szukaj w szczególności:
- widoków/komponentów obsługujących URL związany z "portalem pilota" (prawdopodobnie coś jak `resources/views/pilot-portal/`, `resources/views/portal/`, albo komponent Livewire typu `PilotPortal`, `TripPortal`),
- banera "Podgląd: biuro (lista) — tylko odczyt" z opisem trybu i przyciskiem "Zakończ podgląd",
- paska zakładek: Informacje, Program, Checklista, Obecność, Rozliczenie, Hotele, Dokumenty, Kontakt,
- widoku zakładki "Informacje" — kartę wycieczki ze zdjęciem, nazwą, datami, miastem; sekcję "Podstawienie i wyjazd" (Status, Adres podstawienia autokaru, Godzina podstawienia/wyjazdu/powrotu, Liczba osób); sekcję "Trasy przejazdu" (lista dni z trasą lub "—"); sekcję "Transport" (Firma transportowa, Kierowca, Telefon kierowcy, Rejestracja autokaru); sekcję "Kontakty" (Klient + telefon + e-mail, Pilot + telefon),
- widoku zakładki "Program" — nagłówek "Pełny dostęp do wycieczki do [data]", sekcję "Podstawienie i wyjazd", a potem listę dni z punktami programu (nazwa punktu + zakres godzin),
- widoku zakładki "Hotele" — komunikat "Po zakwaterowaniu wpisz numery pokoi nadane przez hotel — widoczne dla biura i w dokumentach", nagłówek per noc, i **tabelę z jednym wierszem na osobę** (np. "Nocleg autokar (1/44)", "(2/44)" … "(44/44)", każdy z polem tekstowym na numer pokoju — to pole nazywa się prawdopodobnie coś w stylu `room_number` per uczestnik/miejsce).

Wypisz mi te pliki, zanim przejdziesz dalej, żebym mógł potwierdzić, że to właściwe miejsce.

### Krok 2 — cel przeprojektowania
Chcę zachować dokładnie te same dane i tę samą funkcjonalność (w tym tryb tylko-do-odczytu dla biura vs tryb edycji dla pilota), ale w nowej, bardziej nowoczesnej i mobilnej prezentacji, zgodnie z `mockup-reference-portal-pilota.html`:

1. **Baner podglądu** — zachowaj treść i funkcję (informacja o trybie + przycisk "Zakończ podgląd"), ale w formie zaokrąglonej, subtelnie podkolorowanej karty zamiast pełnej szerokości paska ostrzegawczego u samej góry.
2. **Karta wycieczki (hero)** — zdjęcie wycieczki jako tło z nazwą, datami i miastem nałożonymi na dole zdjęcia (ciemny gradient pod tekstem dla czytelności), zamiast małego kwadratowego zdjęcia z osobnym tekstem obok.
3. **Pasek zakładek jako przewijane poziomo pigułki** (Informacje, Program, Checklista, Obecność, Rozliczenie, Hotele, Dokumenty, Kontakt) — na wąskim ekranie użytkownik przewija palcem w bok, żadna zakładka się nie łamie do dwóch linii ani nie ucina. Na szerszym ekranie pigułki mogą się zawijać do jednego pełnego rzędu.
4. **Zakładka "Informacje"** — pogrupuj dane w karty: "Podstawienie i wyjazd" jako siatka 2×2 kluczowych danych (godziny, liczba osób) ze statusem jako badge w rogu karty, "Trasy przejazdu" jako zwarta lista dzień→trasa (lub "brak trasy" w stonowanym kolorze zamiast pustego myślnika), "Transport" jako druga siatka 2×2, "Kontakty" jako lista z awatarami-inicjałami (kółko z dwiema literami imienia i nazwiska, różny kolor dla klienta i dla pilota) zamiast surowych etykieta/wartość. Na desktopie (≥640px) te karty mogą iść w układzie dwukolumnowym, na mobile w jednej kolumnie pionowo.
5. **Zakładka "Program"** — zamień jeden bardzo długi scroll na **rozwijane akordeony per dzień** (domyślnie dzień dzisiejszy/najbliższy rozwinięty, pozostałe zwinięte, z liczbą punktów widoczną w nagłówku dnia, np. "Dzień 2 · 5 punktów"), żeby pilot mógł szybko przejść do interesującego go dnia bez przewijania całego programu wycieczki na telefonie.
6. **Zakładka "Hotele" — najważniejsza zmiana.** Zamiast tabeli z 44 identycznymi wierszami "Nocleg autokar (X/44)" (każdy z jednym polem numeru pokoju), zrób **zwartą siatkę ponumerowanych pól** pogrupowaną nagłówkiem "Noc X — numery pokoi" z podtytułem typu pokoju i liczby miejsc (np. "Nocleg autokar · 44 miejsca · 1 os./pokój"). Siatka ma 4 kolumny na mobile i więcej (np. 8) na desktopie — każde pole to mały numerowany kafelek z inputem na numer pokoju. To wyłącznie zmiana prezentacji: pod spodem nadal musi być dokładnie to samo pole/rekord na osobę co dziś (44 osobne wartości do zapisania), tylko pokazane w znacznie bardziej zwartej formie zamiast tabeli z 44 wierszami.
7. Pozostałe zakładki (Checklista, Obecność, Rozliczenie, Dokumenty, Kontakt) — zachowaj ich obecną zawartość i funkcjonalność, ale zastosuj do nich tę samą stylistykę kart co w pozostałych zakładkach (zaokrąglone rogi, spójne odstępy, karta na biały tle zamiast płaskich sekcji rozdzielonych liniami), żeby całość portalu była spójna.

### Krok 3 — wymagania techniczne
- Zachowaj wszystkie istniejące trasy/route'y, nazwy zmiennych przekazywanych do widoków, logikę trybu tylko-do-odczytu vs edycji (podgląd biura kontra widok pilota), oraz WSZYSTKIE dane pokazywane w każdej zakładce — nic nie chowaj bezpowrotnie, jeśli coś ma zniknąć z głównego widoku, przenieś to do rozwijanego elementu (np. akordeon), a nie usuwaj.
- Pole numeru pokoju w zakładce "Hotele" musi pozostać w pełni funkcjonalne (możliwość wpisania i zapisania numeru dla każdej z 44 pozycji) — zmienia się tylko sposób prezentacji (siatka zamiast tabeli), a nie liczba edytowalnych pól ani sposób ich zapisu.
- Zachowaj responsywność zgodnie z podejściem mobile-first z pliku referencyjnego: buduj i testuj najpierw na wąskim viewporcie (375–420px), potem sprawdź układ na szerszym ekranie.
- Jeśli w projekcie jest już zdefiniowany motyw/kolory (Tailwind config, custom CSS), trzymaj się istniejącej palety; jeśli nie, użyj kolorów i skali odstępów zdefiniowanych w `mockup-reference-portal-pilota.html` (sekcja `<style>` na górze pliku, z komentarzem opisującym paletę) jako punktu odniesienia — ta sama paleta co w plikach referencyjnych dla panelu admina (Transport/Hotele/Pilot), żeby cała aplikacja (backoffice + portal) miała spójną tożsamość wizualną.
- Nie zmieniaj nazw tras, kontrolerów, nazw pól w bazie danych — to wyłącznie zmiana warstwy prezentacji (widoki Blade/Livewire).

### Krok 4 — proces pracy
1. Najpierw pokaż mi plan zmian (które pliki widoków zmienisz, jak podzielisz komponenty — np. czy akordeon dni w Programie i siatka pokoi w Hotelach będą osobnymi komponentami Blade/Livewire) — zanim zaczniesz edytować pliki.
2. Po akceptacji planu zaimplementuj zmiany etapami: najpierw baner podglądu + karta hero + pasek zakładek (elementy wspólne dla wszystkich podstron), potem zakładka "Informacje", potem "Program" (akordeon), na końcu "Hotele" (siatka pokoi — najbardziej złożona zmiana).
3. Po każdej zakładce sprawdź wygląd na symulowanym wąskim ekranie (devtools → tryb mobilny, ok. 390px szerokości) i na szerokim ekranie.
4. Na koniec podsumuj, które pliki zostały zmienione i czy któraś decyzja (np. sposób grupowania pól numeru pokoju w komponent siatki) wymagała ręcznego wyboru z mojej strony.

Nie usuwaj żadnej funkcjonalności ani danych. Jeśli coś jest niejasne (np. dokładna struktura danych dla numerów pokoi, albo czy zakładki "Obecność"/"Rozliczenie"/"Dokumenty" mają dodatkową złożoność, której nie widziałem na przesłanych zrzutach ekranu), zapytaj mnie przed wprowadzeniem zmian zamiast zgadywać.

---

## Wskazówka
1. Wrzuć plik `mockup-reference-portal-pilota.html` do repo (np. do `resources/mockups/` albo tymczasowo do katalogu głównego).
2. Otwórz go w przeglądarce i przetestuj też w trybie mobilnym devtools (Ctrl/Cmd+Shift+M), żeby zobaczyć układ mobile-first w akcji — potem dołącz zrzut ekranu z obu widoków (mobile i desktop) razem z 3 zrzutami obecnego widoku do wiadomości w Cursorze.
3. Ten widok, w przeciwieństwie do panelu admina, będzie realnie używany na telefonach przez pilotów w trasie — warto po wdrożeniu przetestować go na prawdziwym telefonie albo w Chrome DevTools na kilku szerokościach (375px, 390px, 428px), nie tylko "zmniejszyć okno przeglądarki".
4. Po zakończeniu prac możesz usunąć plik `mockup-reference-portal-pilota.html` z repo — służył tylko jako wzorzec, nie jest częścią aplikacji.
