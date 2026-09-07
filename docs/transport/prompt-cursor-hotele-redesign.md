# Prompt dla Cursor — przeprojektowanie sekcji "Hotele"

Skopiuj poniższy tekst i wklej jako polecenie w Cursorze (najlepiej w trybie Agent, z otwartym repo). Dołącz też plik `mockup-reference-hotele.html` do repo oraz zrzuty ekranu obecnego widoku.

---

## PROMPT

W repo, w katalogu głównym (lub tam gdzie wygodniej), znajduje się plik **`mockup-reference-hotele.html`** — otwórz go i przeanalizuj jako wzorzec wizualny. To statyczna makieta (czysty HTML/CSS, bez PHP/Filament) pokazująca DOKŁADNY układ, siatkę, kolory, zakładki wewnętrzne i hierarchię, jaką mam na myśli dla przebudowanej sekcji Hotele. Traktuj ten plik jako źródło prawdy dla warstwy wizualnej — odwzoruj jego strukturę CSS (klasy, grid, kolory, spacing, podział na 3 zakładki) w komponentach Filament/Blade, zamiast wymyślać własny układ od zera. Paleta kolorów i skala odstępów jest identyczna jak w poprzednio przebudowanej sekcji Transport (`mockup-reference-transport.html`), żeby cały moduł Operacje wyglądał spójnie — jeśli ten plik też jest w repo, potraktuj go jako dodatkowy punkt odniesienia dla spójności stylu między zakładkami.

Pracujemy w aplikacji Laravel + Filament. Chcę przeprojektować widok/formularz sekcji **"Hotele"** w module Imprezy (zakładka Operacje → Hotele), bez usuwania jakiejkolwiek istniejącej funkcjonalności, pól, walidacji, relacji ani logiki obliczeniowej — w tym logiki wyliczania kosztów noclegów, przypisań miejsc w pokojach i statusów płatności.

### Krok 1 — znajdź odpowiednie pliki
Zanim zaczniesz zmieniać kod, znajdź i pokaż mi listę plików odpowiedzialnych za tę sekcję. Szukaj w szczególności:
- Resource/Page/RelationManager Filament lub komponent Livewire dla "Hotele" powiązany z modelem Imprezy (adres widoczny w przeglądarce to `/admin/events/{id}/hotel-planning`, więc szukaj plików zawierających "hotel-planning", "HotelPlanning", "HotelRelationManager" itp.),
- pliki definiujące sekcję "Zajętość (occupancy)" — statystyki (uczestnicy, opiekunowie, obsługa, pilot, kierowcy, potrzebne miejsca) oraz tabelę "Plan hotelowy" (Obiekt, Dzień, Miejsca w pokojach, Przypisani, Wolne, Zajętość),
- pliki definiujące sekcję "Finanse noclegów (per noc)" — tabelę (Noc, Hotel, Koszt planu pokoi, Plan rozliczenia, Zapłacono, Status) z przyciskiem "Płatności i rezerwacja" per wiersz,
- pliki definiujące panel "Hotele — Paryż" z listą nocy w sidebarze (karty "Noc 1", "Noc 2"…), przełącznikiem "Krok 1 / Krok 2", przyciskami "Zapisz plan" i "Przywróć z szablonu",
- pliki definiujące "Krok 1 — ile jakich pokoi, po ile" — wyszukiwarkę hotelu/kontrahenta, checkbox "Szukaj we wszystkich kontrahentach", przyciski "Ten sam hotel na wszystkie noce" i "Kopiuj strukturę na wszystkie noce", pola "Co hotel oferuje w cenie" i "Uwagi do tej nocy",
- pliki definiujące "Cennik noclegów" (radio: z linii pokoi / stała kwota — na poziomie całej imprezy i per noc),
- pliki definiujące "Struktura pokoi" — tabelę typów pokoi (Typ pokoju, Ilość, Osób/pokój, Cena za, Cena, Waluta, checkbox PLN, Suma, Rola, Usuń) z przyciskiem "Dodaj typ pokoju" i wierszem "RAZEM",
- pliki definiujące "Szybkie kopiowanie ułożonej struktury" (źródło + checkboxy docelowych nocy + "Wykonaj kopiowanie"),
- pliki definiujące "Krok 2 — Lista osób (1 wiersz = 1 miejsce)" (jeśli już zaimplementowany — jeśli nie, zostaw miejsce na niego bez zmian w logice),
- pliki definiujące "Brak dodatkowych usług hotelu" / dodawanie usług dodatkowych (bankiet, DJ itp.),
- pliki definiujące "Dokumenty i korespondencja z obiektem" — listę dokumentów imprezy oraz formularz "Log korespondencji z obiektem" (Kierunek, Data kontaktu, Hotel/kontrahent, Noc, Osoba kontaktowa, Temat, Treść/ustalenia, Załącznik, przycisk "Dodaj wpis"),
- pliki definiujące "Karteczki — ustalenia hotelowe" (notatki + pole dodawania nowej notatki).

Wypisz mi te pliki, zanim przejdziesz dalej, żebym mógł potwierdzić, że to właściwe miejsce.

### Krok 2 — cel przeprojektowania
Obecny widok to jeden bardzo długi, gęsty scroll zawierający: statystyki zajętości, tabelę zajętości per noc, osobną tabelę finansów per noc (z powtórzoną kolumną "Noc"), panel wyboru nocy z boku, formularz "Krok 1" (hotel, cennik, struktura pokoi), narzędzie szybkiego kopiowania, przyciski zapisu, a NIŻEJ jeszcze: dodatkowe usługi, dokumenty, log korespondencji (zawsze rozwinięty formularz) i notatki. Użytkownik musi przewijać kilka ekranów, żeby dotrzeć do rzeczy używanych rzadziej.

Chcę **nowego układu**, zachowującego wszystkie pola i całą logikę, ale zorganizowanego w **3 wewnętrzne zakładki** w obrębie widoku Hotele (dokładnie tak jak w `mockup-reference-hotele.html`):

**Zakładka 1 — "Przegląd nocy"**
Połącz obecną tabelę "Zajętość" (Obiekt, Dzień, Miejsca w pokojach, Przypisani, Wolne, Zajętość) z tabelą "Finanse noclegów" (Noc, Hotel, Koszt planu pokoi, Plan rozliczenia, Zapłacono, Status) w **jedną tabelę**, jeden wiersz = jedna noc, z kolumnami: Noc, Hotel, Miejsca/przypisani, % zajętości (jako kolorowy pill: czerwony przy niskiej zajętości, zielony przy wysokiej — zdefiniuj sensowny próg lub zostaw to jako TODO z komentarzem, jeśli próg nie jest jeszcze nigdzie zdefiniowany w kodzie), Koszt planu, Zapłacono, przycisk akcji "Płatności" (który ma robić dokładnie to co dziś robi "Płatności i rezerwacja"). Statystyki ogólne (uczestnicy, opiekunowie, obsługa, pilot, kierowcy, potrzebne miejsca, suma noclegów, zapłacono) przenieś do paska/kafelków na samej górze widoku (nad zakładkami), widocznych niezależnie od tego, którą zakładkę użytkownik ma otwartą.

**Zakładka 2 — "Planowanie pokoi"**
Layout dwukolumnowy: z lewej wąski sidebar z kartami nocy (Noc 1, Noc 2, Noc 3, Noc 4) — każda karta pokazuje nazwę hotelu (lub "Hotel nie wybrany") i sumę kosztu, klikalna, podświetlona gdy aktywna — to zamiennik obecnej listy "NOCE" w lewej kolumnie. Z prawej, dla wybranej nocy: pole wyszukiwania hotelu/kontrahenta + checkbox "szukaj we wszystkich kontrahentach", przyciski szybkich akcji ("Ten sam hotel na wszystkie noce", "Kopiuj strukturę na wszystkie noce") w nagłówku karty zamiast osobnych przycisków rozrzuconych w formularzu, pola "Co hotel oferuje w cenie" i "Uwagi do tej nocy" obok siebie, przełącznik cennika (z linii pokoi / stała kwota) jako para "chipów" zamiast zwykłych radio buttonów, tabela struktury pokoi skrócona wizualnie (mniejszy padding, bez kolumny Waluta jeśli w 99% przypadków to PLN — ale zachowaj pole w formularzu/logice, tylko pokaż je bardziej dyskretnie, np. jako dropdown w komórce zamiast osobnej szerokiej kolumny), wiersz "Razem noc X" wyrównany do prawej obok przycisku "Dodaj typ pokoju". Sekcja "Szybkie kopiowanie ułożonej struktury" jako kompaktowy pasek pod główną kartą (źródło + checkboxy docelowych nocy + przycisk), a nie osobna, w pełni rozwinięta sekcja. Przyciski "Zapisz" / "Zapisz i przejdź do listy osób" trzymaj widoczne na dole tej zakładki (opcjonalnie sticky).

**Zakładka 3 — "Usługi i dokumenty"**
Trzy kompaktowe podsumowania na górze (Dodatkowe usługi, Dokumenty i korespondencja, Karteczki hotelowe) pokazujące stan skrócony (np. "4 dokumenty", "Brak notatek — dodaj pierwszą"). Formularz "Log korespondencji z obiektem" — obecnie zawsze w pełni rozwinięty formularz — przenieś do rozwijanego akordeonu/collapsible ("+ Dodaj wpis do logu korespondencji"), żeby nie zajmował miejsca, gdy nikt aktualnie nie dodaje wpisu. Lista istniejących dokumentów i wpisów korespondencji zostaje widoczna bez rozwijania. Notatki (karteczki) — zachowaj obecną funkcjonalność dodawania/wyświetlania, tylko w spójnej stylistyce z resztą kart.

### Krok 3 — wymagania techniczne
- Zachowaj wszystkie istniejące `name()` pól formularza, reguły walidacji, `afterStateUpdated`, `live()`, relacje oraz CAŁĄ logikę wyliczeń: koszt struktury pokoi (ilość × cena, z uwzględnieniem waluty), przełącznik "z linii pokoi" vs "stała kwota" (i na poziomie całej imprezy, i per noc — to są dwa niezależne przełączniki, nie myl ich), przeliczanie zajętości (przypisani / miejsca w pokojach), status płatności, kopiowanie struktury między nocami, "ten sam hotel na wszystkie noce". Nie zmieniaj logiki biznesowej, tylko układ/wygląd i grupowanie w zakładki.
- Jeśli obecny "Krok 1 / Krok 2" to już jest jakiś mechanizm zakładek/wizardu w Filamencie (np. `Wizard` lub `Tabs`), zagnieźdź go wewnątrz nowej zakładki "Planowanie pokoi" zamiast go duplikować czy usuwać.
- Użyj natywnych komponentów Filamenta do grupowania i zakładek: `Tabs`, `Section`, `Grid`, `Fieldset`, `Group` — a jeśli obecny kod jest custom Blade/Livewire, dostosuj się do istniejącego stylu w projekcie zamiast przepisywać wszystko na Filament od zera.
- Jeśli w projekcie jest już zdefiniowany motyw/kolory Filamenta (custom theme), trzymaj się istniejącej palety i configu; jeśli nie, użyj kolorów i skali odstępów zdefiniowanych w `mockup-reference-hotele.html` (sekcja `<style>` na górze pliku, z komentarzem opisującym paletę) jako punktu odniesienia.
- Zachowaj responsywność — layout dwukolumnowy w zakładce "Planowanie pokoi" powinien spadać do jednej kolumny na wąskich ekranach (sidebar nocy nad panelem, nie obok).
- Nie zmieniaj nazw tabeli/kolumn w bazie danych, nie migruj niczego — to wyłącznie zmiana warstwy prezentacji.
- Domyślnie otwarta zakładka wewnętrzna to "Przegląd nocy" (to, co użytkownik najczęściej chce sprawdzić najpierw), ale zapamiętaj/przekaż stan aktywnej nocy między zakładkami "Przegląd" a "Planowanie", jeśli to możliwe bez większej przebudowy — jeśli wymagałoby to istotnej zmiany w warstwie stanu, zostaw to jako propozycję do mojej decyzji, nie wprowadzaj na siłę.

### Krok 4 — proces pracy
1. Najpierw pokaż mi plan zmian (które komponenty Filament/Blade zmienisz, jak ma wyglądać nowa struktura `form()`/zakładek w pseudo-kodzie, i którą istniejącą logikę PHP/Livewire zostawiasz bez ruszania) — zanim zaczniesz edytować pliki.
2. Po akceptacji planu zaimplementuj zmiany krok po kroku, zaczynając od zakładki "Przegląd nocy" (najprostsza, najmniej ryzykowna), potem "Planowanie pokoi", na końcu "Usługi i dokumenty".
3. Po każdej zakładce uruchom istniejące testy (jeśli są) dla tego modułu i sprawdź, czy formularz się kompiluje bez błędów.
4. Na koniec podsumuj, które pliki zostały zmienione i czy któraś z logik (np. próg kolorowania % zajętości, sposób łączenia dwóch tabel w jedną) wymagała ręcznej decyzji z mojej strony.

Nie usuwaj żadnej funkcjonalności. Jeśli coś jest niejasne (np. dokładna reguła progu zajętości do kolorowania, albo czy "Krok 2 — lista osób" jest już gdzieś zaimplementowany), zapytaj mnie przed wprowadzeniem zmian zamiast zgadywać.

---

## Wskazówka
1. Wrzuć plik `mockup-reference-hotele.html` do repo (np. do `resources/mockups/` albo tymczasowo do katalogu głównego) — Cursor musi mieć do niego dostęp w kontekście projektu.
2. Jeśli wcześniej wdrażałeś przebudowę sekcji Transport z plikiem `mockup-reference-transport.html`, warto dorzucić go też do tej sesji — dzięki temu Cursor utrzyma spójną stylistykę między obiema zakładkami Operacji.
3. Otwórz `mockup-reference-hotele.html` w przeglądarce i zrób z niego zrzut ekranu, a potem dołącz zarówno plik `.html`, jak i zrzuty ekranu obecnego widoku (masz je już zrobione) do wiadomości w Cursorze.
4. Po zakończeniu prac możesz usunąć pliki `mockup-reference-*.html` z repo — służyły tylko jako wzorzec, nie są częścią aplikacji.
