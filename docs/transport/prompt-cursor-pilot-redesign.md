# Prompt dla Cursor — przeprojektowanie sekcji "Pilot"

Skopiuj poniższy tekst i wklej jako polecenie w Cursorze (najlepiej w trybie Agent, z otwartym repo). Dołącz też plik `mockup-reference-pilot.html` do repo oraz zrzuty ekranu obecnego widoku (3 screeny: górna część z odprawą/przypisaniem/gotówką planowaną, środkowa z uwagami/checklistą, dolna z rozliczeniem gotówki i wydatkami).

---

## PROMPT

W repo, w katalogu głównym (lub tam gdzie wygodniej), znajduje się plik **`mockup-reference-pilot.html`** — otwórz go i przeanalizuj jako wzorzec wizualny. To statyczna makieta (czysty HTML/CSS, bez PHP/Filament) pokazująca DOKŁADNY układ, siatkę, kolory, zakładki wewnętrzne i hierarchię, jaką mam na myśli dla przebudowanej sekcji Pilot. Traktuj ten plik jako źródło prawdy dla warstwy wizualnej — odwzoruj jego strukturę CSS (klasy, grid, kolory, spacing, podział na 4 zakładki) w komponentach Filament/Blade, zamiast wymyślać własny układ od zera. Paleta kolorów i skala odstępów jest identyczna jak w poprzednio przebudowanych sekcjach Transport i Hotele (`mockup-reference-transport.html`, `mockup-reference-hotele.html`) — jeśli te pliki też są w repo, potraktuj je jako dodatkowy punkt odniesienia dla spójności stylu w całym module Operacje.

Pracujemy w aplikacji Laravel + Filament. Chcę przeprojektować widok/formularz sekcji **"Pilot"** w module Imprezy (zakładka Operacje → Pilot), bez usuwania jakiejkolwiek istniejącej funkcjonalności, pól, walidacji, relacji ani logiki obliczeniowej — w tym CAŁEJ logiki rozliczeń gotówkowych, wymiany walut, sald i wydatków pilota.

### Krok 1 — znajdź odpowiednie pliki
Zanim zaczniesz zmieniać kod, znajdź i pokaż mi listę plików odpowiedzialnych za tę sekcję. Szukaj w szczególności:
- Resource/Page/RelationManager Filament lub komponent Livewire dla "Pilot" powiązany z modelem Imprezy,
- pliki definiujące pasek "Udostępnienie portalu" (Podgląd jako [pilot], Udostępnij pilotowi, Wyślij e-mail do pilota, Udostępniona, toggle "Portal: wymiana" / "Portal: zbiórka"),
- pliki definiujące sekcję "Odprawa" (Status odprawy, Uwagi do odprawy),
- pliki definiujące sekcję "Przypisanie" — wybór pilota/opiekuna z kontrahentów (checkbox "Szukaj we wszystkich kontrahentach"), kartę kontrahenta, kontakty powiązane, przyciski Edytuj dane kontrahenta / Dodaj kontakt / Edytuj kontakt, pola Data urodzenia pilota, PESEL pilota, informację "Konto panelu pilota",
- pliki definiujące sekcję "Gotówka — Planowana / Wypłacona" (Kwota + Waluta planowanej gotówki, "Plan zapisany" z timestampem, toggle "Wypłacona — zatwierdź rzeczywistą wypłatę", podsumowanie Wypłaconej kwoty, przyciski "Zmień / dopłać / dodaj walutę" i "Cofnij wypłatę"),
- pliki definiujące "Uwagi dla pilota" (rich text),
- pliki definiujące sekcję "Checklista" — link do szablonów, pasek postępu (X/Y), dropdown "Załaduj uniwersalny szablon" + przycisk "Załaduj punkty", linki "Utwórz szablon" / "Zarządzaj szablonami", listę punktów z checkboxami i usuwaniem (X), pole "Dodaj własny punkt checklisty" + przycisk "Dodaj",
- pliki definiujące "Rozliczenie — gotówka i wymiana walut": podsekcję "Wypłata gotówki pilotowi" (Waluta, Kwota wydana pilotowi, Data wypłaty, Komentarz, przyciski "Zapisz wypłatę" / "Uzupełnij z wyliczenia"), podsekcję "Gotówka dla pilota" (tabela: Waluta, Do przygotowania, Od biura, Po wymianie, Wydane gotówką, Saldo końcowe/do zwrotu, pole "Zwrócono do biura", przycisk "Zapisz zwrot"), podsekcję "Wydatki / koszty pilota" (Gotówka od biura, link "Odśwież z wycieczki", tabela: Punkt/Set, Zapłacono, Plik/zdjęcie z przyciskami "Zrób zdjęcie" / "Wybierz plik", Komu, Uwagi, Edytuj — z wierszami Suma EUR / Suma PLN, formularz "Dodaj wydatek": Opis, Kwota, Waluta, Nr faktury/paragonu, przycisk "Dodaj wydatek").

Wypisz mi te pliki, zanim przejdziesz dalej, żebym mógł potwierdzić, że to właściwe miejsce.

### Krok 2 — cel przeprojektowania
Obecny widok to jeden bardzo długi scroll (3 pełne ekrany), w którym **ta sama informacja o gotówce pilota pojawia się kilka razy w różnych miejscach**: kwota wypłacona (2500 PLN + 800 EUR) jest pokazywana w sekcji "Gotówka — Planowana/Wypłacona" na górze, a potem PONOWNIE w "Wypłata gotówki pilotowi" wewnątrz "Rozliczenie — gotówka i wymiana walut" na dole. To myląca duplikacja i użytkownik nie wie, gdzie faktycznie edytować dane.

Chcę **nowego układu** w postaci **4 wewnętrznych zakładek** w obrębie widoku Pilot (dokładnie jak w `mockup-reference-pilot.html`), zachowującego wszystkie pola i całą logikę:

**Pasek statusu na górze (widoczny niezależnie od aktywnej zakładki):**
- Nazwa pilota, liczba uczestników.
- Status udostępnienia portalu jako badge + przyciski szybkiego dostępu ("Podgląd jako [pilot]", "Wyślij e-mail do pilota") — to są akcje wykonywane rzadko, ale chcemy je mieć zawsze pod ręką.
- Kafelki statystyk: Status odprawy, Postęp checklisty (X/Y), Wypłacono pilotowi (suma), Do zwrotu (suma, wyróżniona kolorem jeśli > 0) — żeby kluczowe liczby były widoczne bez względu na to, którą zakładkę się przegląda.

**Zakładka 1 — "Odprawa i pilot"**
Dwie karty obok siebie: "Odprawa" (status + uwagi) i "Przypisanie pilota" (wyszukiwarka kontrahenta, karta kontrahenta z kontaktami powiązanymi, przyciski edycji, data urodzenia, PESEL, konto panelu pilota).

**Zakładka 2 — "Gotówka i rozliczenia"**
To najważniejsza zmiana. Połącz trzy dotychczasowe sekcje w JEDEN spójny widok "przepływu gotówki": Plan → Wypłacono → Wydano → Do zwrotu, pokazany jako pozioma sekwencja kafelków (żeby użytkownik widział cały cykl życia gotówki na pierwszy rzut oka, bez przewijania), a pod spodem — akcje edycji tego przepływu (Zmień/dopłać/dodaj walutę, Cofnij wypłatę, Zapisz zwrot, Uzupełnij z wyliczenia) w jednym rzędzie przycisków. WAŻNE: to ma być tylko ZMIANA PREZENTACJI — pod spodem nadal muszą działać te same, osobne formularze/akcje co dziś (zapis planu, zatwierdzenie wypłaty, zapis zwrotu, zmiana wypłaty to różne operacje w bazie), po prostu pokazane w jednym miejscu zamiast w 3 rozjechanych sekcjach z powtórzonymi liczbami. Jeśli po analizie kodu okaże się, że pełne scalenie tych 3 sekcji w jeden komponent wymagałoby ryzykownej przebudowy logiki stanu, zaproponuj mi wariant pośredni (np. wizualne zbliżenie sekcji + wyraźne nagłówki „krok 1/2/3” zamiast pełnego scalenia) i zapytaj, zanim to zrobisz.
Sekcję "Wydatki / koszty pilota" (tabela punktów, zdjęcia, dodawanie wydatku) umieść w rozwijanym akordeonie pod przepływem gotówki — z etykietą pokazującą liczbę pozycji — żeby nie zajmowała miejsca, gdy nikt aktualnie nie przegląda wydatków.

**Zakładka 3 — "Checklista"**
Pasek postępu, wybór szablonu, lista punktów z checkboxami, dodawanie własnego punktu — bez zmian funkcjonalnych, tylko w spójnej stylistyce kart.

**Zakładka 4 — "Uwagi i portal"**
Pełny panel udostępniania portalu (wszystkie przyciski i toggle z paska na samej górze oryginału, tu w rozwiniętej formie) oraz pole "Uwagi dla pilota" (rich text).

Przyciski "Zapisz" / "Anuluj" widoczne na dole widoku (niezależnie od aktywnej zakładki, jeśli to możliwe bez większej przebudowy stanu formularza — jeśli zapis jest per-sekcja, zostaw osobne przyciski zapisu przy każdej zakładce, tak jak działa to dziś, i zaproponuj mi to jako rozwiązanie).

### Krok 3 — wymagania techniczne
- Zachowaj wszystkie istniejące `name()` pól formularza, reguły walidacji, `afterStateUpdated`, `live()`, relacje oraz CAŁĄ logikę: przeliczanie sald gotówkowych per waluta (do przygotowania / od biura / po wymianie / wydane / saldo końcowe), zatwierdzanie i cofanie wypłaty, dopłaty i dodawanie nowych walut, sumowanie wydatków per waluta (EUR/PLN osobno), powiązanie wydatków z pozycjami z wycieczki ("Odśwież z wycieczki"), logikę checklisty (szablony, ładowanie punktów, postęp), oraz logikę udostępniania portalu (generowanie linków, wysyłka e-maila, toggle wymiana/zbiórka). Nie zmieniaj logiki biznesowej, tylko układ/wygląd i grupowanie w zakładki.
- To najbardziej złożona z trzech przebudowywanych sekcji (po Transport i Hotele) — jeśli którekolwiek połączenie sekcji (szczególnie w Zakładce 2) okaże się zbyt ryzykowne technicznie, zatrzymaj się i zapytaj, zamiast composed zgadywać czy na siłę scalać stan formularzy.
- Użyj natywnych komponentów Filamenta do grupowania i zakładek: `Tabs`, `Section`, `Grid`, `Fieldset`, `Group` — a jeśli obecny kod jest custom Blade/Livewire, dostosuj się do istniejącego stylu w projekcie.
- Jeśli w projekcie jest już zdefiniowany motyw/kolory Filamenta (custom theme), trzymaj się istniejącej palety; jeśli nie, użyj kolorów i skali odstępów zdefiniowanych w `mockup-reference-pilot.html` jako punktu odniesienia.
- Zachowaj responsywność — dwukolumnowy layout w Zakładce 1 oraz pozioma sekwencja kafelków w Zakładce 2 powinny spadać do jednej kolumny na wąskich ekranach.
- Nie zmieniaj nazw tabeli/kolumn w bazie danych, nie migruj niczego — to wyłącznie zmiana warstwy prezentacji.

### Krok 4 — proces pracy
1. Najpierw pokaż mi plan zmian (które komponenty zmienisz, jak ma wyglądać nowa struktura zakładek w pseudo-kodzie, i którą logikę PHP/Livewire zostawiasz bez ruszania, ze szczególnym wskazaniem, jak planujesz podejść do scalenia trzech sekcji gotówkowych w Zakładce 2) — zanim zaczniesz edytować pliki.
2. Po akceptacji planu zaimplementuj zmiany etapami: najpierw Zakładka 1 (najprostsza), potem Zakładka 3 i 4 (Checklista, Uwagi i portal — też stosunkowo proste), na końcu Zakładka 2 (Gotówka i rozliczenia — najbardziej ryzykowna).
3. Po każdej zakładce uruchom istniejące testy (jeśli są) i sprawdź, czy formularz się kompiluje bez błędów, a wszystkie akcje (zapis planu, zatwierdzenie wypłaty, cofnięcie wypłaty, zapis zwrotu, dodanie wydatku, załadowanie szablonu checklisty) nadal działają.
4. Na koniec podsumuj, które pliki zostały zmienione i czy któraś z decyzji (szczególnie sposób scalenia sekcji gotówkowych) wymagała ręcznego wyboru z mojej strony.

Nie usuwaj żadnej funkcjonalności. Jeśli coś jest niejasne — a przy tej sekcji spodziewam się, że coś będzie — zapytaj mnie przed wprowadzeniem zmian zamiast zgadywać, szczególnie przy logice rozliczeń gotówkowych, bo błąd tutaj oznacza realne pieniądze się nie zgadzają.

---

## Wskazówka
1. Wrzuć plik `mockup-reference-pilot.html` do repo (np. do `resources/mockups/` albo tymczasowo do katalogu głównego) — Cursor musi mieć do niego dostęp w kontekście projektu.
2. Dorzuć też `mockup-reference-transport.html` i `mockup-reference-hotele.html`, jeśli wcześniej z nich korzystałeś — dla spójności stylu całego modułu Operacje.
3. Otwórz `mockup-reference-pilot.html` w przeglądarce, zrób zrzut ekranu, i dołącz go razem z 3 zrzutami obecnego widoku Pilot do wiadomości w Cursorze.
4. Ta sekcja ma najbardziej wrażliwą logikę finansową ze wszystkich trzech (Transport, Hotele, Pilot) — po wdrożeniu koniecznie przetestuj ręcznie cały cykl: zapisanie planu gotówki → zatwierdzenie wypłaty → dodanie wydatku → zapis zwrotu, żeby upewnić się, że salda się zgadzają tak samo jak przed zmianą wyglądu.
5. Po zakończeniu prac możesz usunąć pliki `mockup-reference-*.html` z repo — służyły tylko jako wzorzec, nie są częścią aplikacji.
