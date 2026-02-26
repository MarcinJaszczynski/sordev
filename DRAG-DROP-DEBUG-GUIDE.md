# Test Drag & Drop dla Kanban Board

## Kroki do przetestowania:

### 1. Sprawdź konsolę przeglądarki
- Otwórz DevTools (F12)
- Przejdź na kartę Console
- Powinieneś zobaczyć komunikaty:
  - "DOM Content Loaded"
  - "SortableJS loaded: true"
  - "Livewire loaded: true"
  - "Kanban board initializing..."
  - "Initializing drag and drop..."
  - "Found containers: X" (gdzie X to liczba kolumn)

### 2. Sprawdź czy są zadania
- Na tablicy Kanban powinny być widoczne zadania w kartach
- Każda karta powinna mieć klasę `.record`
- Kontener zadań powinien mieć klasę `.tasks-container[data-status-id]`

### 3. Test przeciągania
- Najedź myszą na kartę zadania - kursor powinien zmienić się na "grab"
- Kliknij i przytrzymaj kartę zadania
- Rozpocznij przeciąganie - powinieneś zobaczyć w konsoli "Drag started: [ID]"
- Przenieś kartę do innej kolumny
- Upuść kartę - powinieneś zobaczyć w konsoli "Drag ended" z danymi

### 4. Sprawdź czy baza danych się aktualizuje
- Po przeniesieniu zadania sprawdź czy status się zmienił w bazie
- Powinieneś zobaczyć notyfikację o zmianie statusu

## Możliwe problemy:

### 1. SortableJS nie ładuje się
- Sprawdź czy CDN jest dostępny
- Sprawdź połączenie internetowe

### 2. Livewire nie działa
- Sprawdź czy nie ma błędów PHP
- Sprawdź logi Laravela: `tail -f storage/logs/laravel.log`

### 3. Kontenerery nie są znajdowane
- Sprawdź HTML czy mają poprawne klasy i atrybuty
- Sprawdź czy `wire:ignore` nie blokuje aktualizacji

### 4. Uprawnienia
- Sprawdź czy użytkownik ma uprawnienia do modyfikacji zadań
- Metoda `canModifyTask()` musi zwracać `true`

## Debugowanie:

```javascript
// W konsoli przeglądarki:
console.log('Containers:', document.querySelectorAll('.tasks-container[data-status-id]'));
console.log('Records:', document.querySelectorAll('.record'));
console.log('Livewire component:', @this);
```

## Pliki zmodyfikowane:
- `resources/views/filament/resources/task-resource/pages/tasks-kanban-board-page.blade.php`
- Poprawiono selektory CSS
- Dodano lepsze debugowanie
- Poprawiono wywołania Livewire
- Dodano style dla Sortable.js
