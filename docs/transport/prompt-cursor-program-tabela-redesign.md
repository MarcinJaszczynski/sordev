# Prompt dla Cursor — przeprojektowanie tabeli "Program imprezy" + drawer szczegółów

Skopiuj poniższy tekst i wklej jako polecenie w Cursorze (najlepiej w trybie Agent, z otwartym repo). Dołącz też plik `mockup-reference-program-tabela.html` do repo oraz oba zrzuty ekranu (widok tabeli i widok panelu szczegółów punktu programu).

---

## PROMPT

W repo, w katalogu głównym (lub tam gdzie wygodniej), znajduje się plik **`mockup-reference-program-tabela.html`** — otwórz go w przeglądarce i przeanalizuj jako wzorzec wizualny. To statyczna makieta (czysty HTML/CSS, bez PHP/Filament/JS) pokazująca DOKŁADNY układ tabeli punktów programu ORAZ panelu bocznego (drawer) ze szczegółami, który ma się otwierać po kliknięciu w wiersz. Traktuj ten plik jako źródło prawdy dla warstwy wizualnej — odwzoruj jego strukturę CSS (siatka kolumn tabeli, kolory statusów, układ kart z trzema kwotami w drawerze) w komponentach Filament/Blade/Livewire, zamiast wymyślać własny układ od zera. Paleta kolorów jest identyczna jak w poprzednio przebudowanych sekcjach modułu Operacje (Transport, Hotele, Pilot, portal pilota) — jeśli te pliki referencyjne też są w repo, potraktuj je jako dodatkowy punkt odniesienia dla spójności stylu.

Pracujemy w aplikacji Laravel + Filament (prawdopodobnie z niestandardowym komponentem tabeli obsługującym drag&drop, bo obecna tabela pozwala przeciągać punkty programu w obrębie dnia). Chcę przeprojektować **tabelę "Program imprezy"** (widoczną po wyborze dnia z paska u góry) oraz **panel boczny (drawer) ze szczegółami pojedynczego punktu programu**, bez usuwania jakiejkolwiek istniejącej funkcjonalności — w tym drag&drop kolejności, przycisków akcji nad tabelą (Przywróć kolejność ze szablonu, Uporządkuj kolejność, Przelicz godziny z czasu trwania, Napraw sety z szablonu, Dodaj punkt programu), filtrów i wyszukiwarki, oraz CAŁEJ logiki finansowej (3 rodzaje kwot, wpłaty, statusy).

### Krok 1 — znajdź odpowiednie pliki
Zanim zaczniesz zmieniać kod, znajdź i pokaż mi listę plików odpowiedzialnych za ten widok. Szukaj w szczególności:
- komponentu/Resource/RelationManager Filament (lub customowego Livewire) odpowiadającego za listę/tabelę "Program imprezy" powiązaną z modelem Imprezy, z obsługą drag&drop i zakładek dni u góry (Dzień 1–5 + Opcje fakultatywne, każdy z licznikiem punktów),
- komponentu odpowiadającego za pasek "W programie / Wszystkie" (liczniki 42/66) oraz pasek akcji (Przywróć kolejność ze szablonu, Uporządkuj kolejność, Przelicz godziny z czasu trwania, Napraw sety z szablonu, Dodaj punkt programu),
- definicji kolumn obecnej tabeli: Punkt programu (z tagami PROGRAM / POZA KALK.), Uwagi biuro/pilot, Płatnik (dropdown Biuro/Klient/itp.), Finanse (SZABLON/PLAN), Faktura, Zakres, przycisk "Płatności" per wiersz, menu akcji (⋮),
- komponentu/modala odpowiadającego za panel szczegółów punktu programu (ten z drugiego zrzutu ekranu) — sekcje KWOTY (Kalkulacja, Plan, Zapłacono, Pozostało), PLANOWANE (cena za osobę, wielkość grupy, waluta, kalkulacja, płatnik + link Edytuj), WPŁATY (lista + przycisk Dodaj), REZERWACJA (link do wspólnej rezerwacji z modułu Operacje → Rezerwacje), DOKUMENTY/FAKTURY (przycisk +plik), sekcję ZAAWANSOWANE (ID kosztu, Grupa, Źródło, Różnica) — sprawdź, czy to modal, drawer, czy osobna podstrona, i jak dokładnie się otwiera (link, przycisk "Płatności", klik w wiersz?).

Wypisz mi te pliki, zanim przejdziesz dalej, żebym mógł potwierdzić, że to właściwe miejsce.

### Krok 2 — cel przeprojektowania

**A) Tabela punktów programu.** Obecna tabela ma zbyt wiele kolumn tekstowych rozjechanych na całą szerokość ekranu (Uwagi biuro/pilot, Płatnik jako pełny dropdown w kolumnie, Finanse jako gołe etykiety "SZABLON"/"PLAN" z myślnikami, Faktura jako sam tekst "Brak", Zakres jako dwa tagi, osobny szeroki przycisk "Płatności" per wiersz) i wygląda gęsto/nieelegancko. Chcę nowego układu kolumn, dokładnie jak w pliku referencyjnym:

1. **Godzina** — zakres godzin punktu, kompaktowo (dwie linie: start / koniec).
2. **Punkt programu** — nazwa punktu pogrubiona, a pod nią JEDNA linia opisu/tagów w stonowanym kolorze (np. "Program · poza kalkulacją" albo skrócony opis) zamiast osobnych, rozjechanych tagów-pigułek dla każdego atrybutu.
3. **Status rezerwacji** — jedna kolorowa pigułka (np. "Brak" na czerwono, "Potwierdzona" na zielono, "—" na szaro gdy nie dotyczy) zamiast rozbicia na osobne pola.
4. **Status płatności** — analogicznie jedna pigułka ("Brak kwoty" czerwono, "Częściowo" żółto/pomarańczowo, "Zapłacone" zielono).
5. **Kwoty** — trzy wartości w jednej, wąskiej kolumnie, każda w osobnej linijce z małą etykietą z lewej i kwotą z prawej: "Szablon" / "Plan" / "Zapł." — DOKŁADNIE te trzy pojęcia biznesowe, które już istnieją w systemie (cena z szablonu wycieczki, cena planowana po ewentualnych korektach/upustach, suma rzeczywiście zapłacona) — nie zmieniaj tej logiki, tylko pokaż ją w jednej zwartej kolumnie zamiast rozjechanych osobnych pól "SZABLON"/"PLAN" z myślnikami.
6. **Dokument** — mała ikona pliku: wypełniona/kolorowa gdy do punktu jest podpięta faktura/dokument, wyszarzona gdy brak — zamiast tekstu "Brak" / "SZABLON".
7. **Menu akcji (⋮)** — chowa tam mniej używane akcje (np. dotychczasowy przycisk "Płatności" może zostać jako pozycja menu ALBO — jeśli to główna akcja na tym punkcie — może otwierać bezpośrednio drawer opisany w części B, to Twoja decyzja projektowa, zaproponuj mi wariant).

Zachowaj: obsługę drag&drop (przeciąganie punktów w obrębie dnia), przycisk "+Zadanie" pod punktem programu (jeśli to osobna funkcja niepowiązana z powyższymi kolumnami, zdecyduj, czy zostawić go w wierszu czy przenieść do drawer/menu — zapytaj mnie, jeśli to nieoczywiste), filtry i wyszukiwarkę nad tabelą, przyciski akcji zbiorczych nad tabelą (Przywróć kolejność ze szablonu, Uporządkuj kolejność, Przelicz godziny z czasu trwania, Napraw sety z szablonu), pasek dni na górze — ale przenieś go na formę przewijanych poziomo pigułek z licznikiem punktów przy każdym dniu (Dzień 1 · 10 / Dzień 2 · 16 / …), zamiast dużych kwadratowych kafli zajmujących sporo miejsca.

**B) Drawer szczegółów punktu.** Zamiast obecnego panelu (widocznego na drugim zrzucie ekranu) z osobnymi, luźno ułożonymi sekcjami i dużą ilością białej przestrzeni, zrób bardziej zwarty i czytelny panel:
- Nagłówek: nazwa punktu + przycisk zamknięcia, pod spodem jedna linia kontekstu (typ punktu, płatnik, status płatności).
- **Trzy kwoty jako karty obok siebie** (Szablon / Plan / Zapłacono) zamiast rozbicia na 4 osobne kwadratowe kafle (Kalkulacja/Plan/Zapłacono/Pozostało) — a "Pozostało do zapłaty" pokaż jako wyróżniony pasek pod kartami (czerwony, gdy > 0), żeby rzucał się w oczy jako rzecz-do-zrobienia.
- Sekcja **"Wpłaty"** — lista dotychczasowych wpłat (kwota, data, metoda/komentarz) + link/przycisk "Dodaj wpłatę" w nagłówku sekcji (to ma otwierać dokładnie ten sam formularz/modal dodawania wpłaty co dziś, tylko wywoływany z tego miejsca).
- Sekcja **"Rezerwacja"** — bez zmian funkcjonalnych, tylko spójna stylistyka z resztą drawer'a.
- Sekcja **"Dokumenty i faktury"** — każdy podpięty plik jako wiersz z ikoną typu pliku (PDF/JPG/itd.), nazwą, rozmiarem i ikoną podglądu/otwarcia pliku (jeśli obecny kod już pozwala na podgląd pliku, użyj tego mechanizmu; jeśli tylko na pobranie, zostaw pobranie, ale nazwij to trafnie) + przycisk "Dodaj plik" w nagłówku sekcji.
- Sekcja **"Zaawansowane"** (ID kosztu, Grupa, Źródło, Różnica) — jako zwinięty element `<details>`/collapsible, tak jak dziś, bez zmian w zawartości.

### Krok 3 — wymagania techniczne
- Zachowaj wszystkie istniejące nazwy pól, walidację, relacje oraz CAŁĄ logikę: wyliczanie 3 kwot (szablon/plan/zapłacono) i pozostałej kwoty do zapłaty, obsługę wielu walut (widzieliśmy PLN i EUR w tym samym programie — nie zakładaj tylko PLN), status rezerwacji, status płatności, drag&drop kolejności punktów, przeliczanie godzin z czasu trwania, dodawanie/usuwanie wpłat, podpinanie dokumentów/faktur, link do wspólnej rezerwacji z modułu Rezerwacje. Nie zmieniaj logiki biznesowej, tylko układ/wygląd.
- Jeśli obecna tabela to customowy komponent (nie natywny Filament Table) ze względu na drag&drop, zachowaj ten mechanizm przeciągania — nie zastępuj go natywnym komponentem Filamenta, jeśli oznaczałoby to utratę funkcji przeciągania, chyba że jesteś pewien, że nowsza wersja Filamenta to obsługuje i przetestujesz to.
- Drawer może być zaimplementowany jako natywny `Filament\Actions\Action` z `->slideOver()` (jeśli już taki mechanizm istnieje w projekcie) albo jako istniejący customowy modal/panel — użyj tego, co już jest w kodzie, tylko zmień wygląd zawartości zgodnie z częścią B.
- Jeśli w projekcie jest już zdefiniowany motyw/kolory Filamenta (custom theme), trzymaj się istniejącej palety; jeśli nie, użyj kolorów i skali odstępów zdefiniowanych w `mockup-reference-program-tabela.html` jako punktu odniesienia.
- Zachowaj responsywność — jeśli tabela ma wiele kolumn i nie mieści się na węższych ekranach, dopuszczalny jest poziomy scroll tabeli (nie kompresuj kolumn tak bardzo, że tekst staje się nieczytelny).
- Nie zmieniaj nazw tabeli/kolumn w bazie danych, nie migruj niczego — to wyłącznie zmiana warstwy prezentacji.

### Krok 4 — proces pracy
1. Najpierw pokaż mi plan zmian (które komponenty zmienisz, jak ma wyglądać nowa struktura kolumn tabeli i sekcji drawer'a w pseudo-kodzie, którą logikę PHP/Livewire zostawiasz bez ruszania) — zanim zaczniesz edytować pliki.
2. Po akceptacji planu zaimplementuj zmiany etapami: najpierw pasek dni + kolumny tabeli (część A), potem drawer szczegółów (część B).
3. Po każdej części sprawdź, czy drag&drop nadal działa, czy dodawanie wpłaty/dokumentu nadal działa, i czy formularz się kompiluje bez błędów.
4. Na koniec podsumuj, które pliki zostały zmienione i czy któraś decyzja (np. gdzie umieścić przycisk "+Zadanie", albo czy drawer otwiera się klikiem w cały wiersz czy tylko w konkretną ikonę) wymagała ręcznego wyboru z mojej strony.

Nie usuwaj żadnej funkcjonalności. Jeśli coś jest niejasne — szczególnie sposób otwierania drawer'a, obsługa wielowalutowości w mini-zestawieniu kwot w tabeli, albo dokładna definicja progów dla kolorów statusu płatności (co dokładnie znaczy "częściowo" vs "brak kwoty") — zapytaj mnie przed wprowadzeniem zmian zamiast zgadywać.

---

## Wskazówka
1. Wrzuć plik `mockup-reference-program-tabela.html` do repo (np. do `resources/mockups/` albo tymczasowo do katalogu głównego) — Cursor musi mieć do niego dostęp w kontekście projektu.
2. Dorzuć też pozostałe pliki referencyjne z tego modułu (`mockup-reference-transport.html`, `mockup-reference-hotele.html`, `mockup-reference-pilot.html`, `mockup-reference-portal-pilota.html`), jeśli już z nich korzystałeś — dla pełnej spójności stylu.
3. Otwórz `mockup-reference-program-tabela.html` w przeglądarce (pokazuje tabelę i drawer obok siebie na desktopie), zrób zrzut ekranu, i dołącz go razem z 2 zrzutami obecnego widoku do wiadomości w Cursorze.
4. Ta tabela ma drag&drop i sporo interaktywnych elementów (dropdown płatnika, przycisk Płatności, menu ⋮) — po wdrożeniu koniecznie sprawdź ręcznie, że każda z tych interakcji nadal działa dokładnie tak jak przed zmianą wyglądu.
5. Po zakończeniu prac możesz usunąć plik `mockup-reference-program-tabela.html` z repo — służył tylko jako wzorzec, nie jest częścią aplikacji.
