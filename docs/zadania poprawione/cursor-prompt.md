# Zadanie dla Cursora: redesign strony "Zadania" (Laravel + Filament)

## Kontekst
Panel admina bprafa ma stronę `/admin/tasks` z listą zadań i widokiem szczegółów
zadania (dyskusja/komentarze pod zadaniem). Obecny widok jest zbyt gęsty:
wszystkie komentarze są rozwinięte naraz, statusy i priorytety są opisane
tekstem zamiast oznaczone kolorem, a układ nie rozdziela wyraźnie listy od
szczegółów. Celem jest wizualny redesign tej strony — bez zmiany logiki
biznesowej zadań, tylko UI/UX.

Mam gotowy mockup referencyjny (plik `mockup-zadania.html` w załączeniu) —
potraktuj go jako źródło prawdy dla layoutu, tokenów kolorów i zachowania
interakcji. Nie kopiuj mockupu 1:1 jako statycznego HTML — przenieś go na
prawdziwe komponenty Filament/Livewire w tym repo, korzystając z istniejących
modeli (`Task`, komentarzy do zadania, itd. — sprawdź faktyczne nazwy w repo
i dopasuj).

## Zakres zmian

### 1. Layout listy + szczegółów
Przebuduj widok zadań na układ dwukolumnowy:
- lewa kolumna (stała szerokość ok. 360px): lista zadań z wyszukiwarką i
  filtrami statusu (chipy: Wszystkie / Do zrobienia / W trakcie / Zrobione),
  każdy wiersz pokazuje: tytuł, powiązaną imprezę (lub "Wolne / nieprzypisane"),
  status jako kolorowy badge, termin, avatar przypisanej osoby (inicjały).
- prawa kolumna: szczegóły wybranego zadania — nagłówek z tytułem i akcjami
  (Edytuj / Usuń), status + priorytet jako kontrolki, siatka metadanych
  (od/dla, termin, utworzono, zmieniono), opis, sekcja dyskusji, i przypięty
  na dole formularz odpowiedzi.

Sprawdź, czy w Filamencie jest już mechanizm split-view (np. resource z
`->recordUrl()` + osobna strona `ViewTask`, albo customowa strona Livewire) i
wybierz podejście najlepiej pasujące do reszty panelu — nie twórz
równoległej, niespójnej architektury.

### 2. Kolorystyka statusów i priorytetów
Wprowadź spójny system kolorów (zamiast czarnego tekstu na szarym tle):
- Do zrobienia → bursztynowy (`warning`)
- W trakcie → niebieski (`info`)
- Zrobione → zielony (`success`)
- Anulowano → czerwony (`danger`)
- Priorytet pilny → czerwona ikona wykrzyknika; zwykły → szara neutralna ikona

Zastosuj to zarówno w tabeli listy zadań (`TextColumn::badge()->color()`),
jak i w widoku szczegółów.

### 3. Komponent dyskusji — najważniejsza zmiana zachowania
W sekcji komentarzy pod zadaniem:
- **zawsze widoczny jest tylko ostatni (najnowszy) komentarz**,
- wcześniejsze komentarze są **domyślnie zwinięte** pod jednym przełącznikiem
  typu "Pokaż N wcześniejszych wiadomości" / "Ukryj wcześniejsze wiadomości",
- po dodaniu nowego komentarza lista wcześniejszych ponownie się zwija, a
  nowy komentarz staje się jedynym widocznym,
- licznik "N wiadomości" w nagłówku sekcji zawsze pokazuje pełną liczbę,
  niezależnie od stanu zwinięcia.

Zaimplementuj to jako komponent Livewire (`TaskCommentThread` lub analogiczny
w konwencji tego repo) z computed properties `latestComment` i
`earlierComments`, oraz osobny blade component na pojedynczy komentarz
(`x-task-comment`) przyjmujący `variant` (`latest` / `earlier`), żeby ostatni
komentarz miał wyraźnie inne tło (lekko podbite kolorem akcentu) niż starsze.

Referencyjna implementacja (do adaptacji pod realny model komentarzy w repo):
- `app/Livewire/TaskCommentThread.php`
- `resources/views/livewire/task-comment-thread.blade.php`
- `resources/views/components/task-comment.blade.php`

### 4. Tokeny wizualne
Dodaj/scal tokeny kolorów w motywie Filamenta (`resources/css/filament/admin/theme.css`,
albo odpowiedni plik motywu w tym repo), zgodnie z paletą z mockupu:
- akcent marki: `#1C4E80` (granat, spójny z obecnym logiem, ale stonowany)
- tło strony: `#F5F6F8`, powierzchnie: `#FFFFFF`
- kolory statusów jak w punkcie 2, każdy z osobnym jasnym tłem badge'a

Nie zmieniaj globalnego motywu innych zasobów Filamenta bez potrzeby —
ogranicz się do zakresu strony zadań, chyba że akcent `primary` jest już
używany spójnie w całym panelu.

### 5. Responsywność i dostępność
- na wąskich ekranach (<1024px) kolumna listy i szczegółów powinny się
  składać w jedną, z widokiem szczegółów jako osobny "krok" (np. po kliknięciu
  zadania) zamiast ściskania dwóch kolumn obok siebie,
- zachowaj widoczny fokus klawiatury na przełączniku "Pokaż wcześniejsze
  wiadomości" i przyciskach akcji,
- kontrast tekstu i tła badge'y musi spełniać WCAG AA.

## Czego NIE robić
- nie zmieniaj modeli, migracji ani logiki uprawnień/statusów zadań,
- nie usuwaj istniejących akcji (edycja, usuwanie, powiadomienia) — tylko
  przenieś je do nowego layoutu,
- nie wprowadzaj nowej biblioteki UI — trzymaj się Filamenta/Tailwinda już
  używanych w projekcie.

## Definicja ukończenia
- lista zadań i widok szczegółów wyglądają zgodnie z mockupem (kolory,
  odstępy, hierarchia),
- w dyskusji pod zadaniem widoczny jest tylko ostatni komentarz, reszta
  rozwija się na żądanie i chowa z powrotem po dodaniu nowej odpowiedzi,
- statusy i priorytety mają spójne kolorowe oznaczenia w liście i w szczegółach,
- strona działa poprawnie na szerokim ekranie i na wąskim (mobile/tablet),
- brak regresji w istniejących testach Filament/Livewire dla modułu zadań —
  jeśli testów brakuje, dodaj podstawowy test na zachowanie "expand/collapse"
  komponentu dyskusji.
