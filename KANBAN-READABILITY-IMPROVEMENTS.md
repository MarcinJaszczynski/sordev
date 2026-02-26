# Poprawki Czytelności Kanban Board

## Wprowadzone Zmiany

### 1. Poprawa Kontrastów Tekstowych

#### Nagłówki Kolumn
- **Jasny tryb**: Zmiana z `text-gray-400` na `text-gray-900`
- **Ciemny tryb**: Dodano `text-gray-100` 
- **Dodatkowy efekt**: Gradient tła i border dla nagłówków
- **Text shadow**: Dodano cień tekstu w trybie ciemnym dla lepszej czytelności

#### Tytuły Zadań
- **Jasny tryb**: `text-gray-950` z `font-bold` i większy rozmiar (`text-base`)
- **Ciemny tryb**: `text-gray-50` 
- **Waga fontu**: Zwiększona z `font-semibold` na `font-bold`

#### Opisy Zadań
- **Jasny tryb**: Zmiana z `text-gray-700` na `text-gray-800`
- **Ciemny tryb**: Zmiana z `text-gray-300` na `text-gray-200`
- **Waga fontu**: Zwiększona z `font-normal` na `font-medium`

### 2. Poprawki Elementów Interfejsu

#### Badge'y i Liczniki
- **Kontrast**: Wszystkie badge'y otrzymały border dla lepszej widoczności
- **Kolory**: Używamy `font-black` dla kluczowych liczników
- **Tła**: Ciemniejsze tła w jasnym trybie, jaśniejsze w ciemnym

#### Meta Informacje (Przypisania, Daty)
- **Tła**: `bg-gray-200/dark:bg-gray-700` zamiast `bg-gray-50/dark:bg-gray-600`
- **Tekst**: `text-gray-900/dark:text-gray-100` z `font-bold`
- **Bordery**: Dodano `border-gray-400/dark:border-gray-600`

#### Przyciski Akcji
- **Kontrast**: Wszystkie przyciski otrzymały border
- **Hover states**: Ciemniejsze kolory hover dla lepszej widoczności
- **Ikony**: Bardziej kontrastowe kolory ikon

### 3. Kontener i Kolumny

#### Tła Kolumn
- **Jasny tryb**: Zmiana z `bg-gray-200` na `bg-gray-100`
- **Ciemny tryb**: Zmiana z `bg-gray-800` na `bg-gray-900`
- **Bordery**: Dodano `border-gray-300/dark:border-gray-700`

#### Karty Zadań
- **Cienie**: Wzmocnione box-shadow dla lepszej separacji
- **Bordery**: 2px border zamiast 1px
- **Hover efekty**: Bardziej wyraziste hover states
- **Klasa CSS**: Dodano `.task-card` dla konsystentnego stylowania

### 4. Filtry i Kontrolki

#### Pola Wejściowe
- **Bordery**: Zmiana z `border-none` na `border-2`
- **Kolory**: `border-gray-400/dark:border-gray-600`
- **Tła**: `bg-white/dark:bg-gray-800`
- **Focus states**: Bardziej wyraziste `focus:border-primary-500`

#### Przyciski
- **Bordery**: Dodano `border-gray-400/dark:border-gray-600`
- **Ring**: Zwiększony z `ring-1` na `ring-2`

### 5. Dodatkowe Style CSS

```css
/* Enhanced contrast and readability */
.record {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.dark .record {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.2);
}

/* Dark mode text improvements */
.dark h3, .dark h4 {
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
}

/* Column headers more visible */
.kanban-column-header {
    background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
    border: 1px solid #d1d5db;
}

.dark .kanban-column-header {
    background: linear-gradient(135deg, #374151 0%, #1f2937 100%);
    border: 1px solid #4b5563;
}

/* Task cards with better contrast */
.task-card {
    background: #ffffff;
    border: 2px solid #e5e7eb;
}

.dark .task-card {
    background: #1f2937;
    border: 2px solid #4b5563;
}
```

### 6. Specjalne Elementy

#### Paski Postępu (Podzadania)
- **Wysokość**: Zwiększona z `h-2` na `h-3`
- **Bordery**: Dodano border dla paska tła
- **Kolory**: Bardziej nasycone zielone dla wypełnienia

#### Pilne Zadania i Przeterminowane
- **Tła**: Bardziej kontrastowe tła dla alertów
- **Tekst**: `font-black` dla ważnych komunikatów
- **Kolory**: `text-red-900/dark:text-red-100` dla przeterminowanych

### 7. Empty States
- **Tekst**: Zmiana z `text-gray-400` na `text-gray-700/dark:text-gray-300`
- **Waga**: Dodano `font-semibold`

## Rezultat

Po wprowadzeniu tych zmian:
- **Znacznie lepszy kontrast** w obu trybach (jasnym i ciemnym)
- **Wyraźniejszy tekst** we wszystkich elementach
- **Lepsze rozdzielenie wizualne** między elementami
- **Bardziej profesjonalny wygląd** z zachowaniem nowoczesnego designu
- **Zgodność z WCAG 2.1** dla kontrastów tekstowych
- **Lepsza czytelność** na wszystkich rozmiarach ekranów

Wszystkie zmiany zachowują oryginalną funkcjonalność, poprawiając jedynie aspekty wizualne i dostępność interfejsu.
