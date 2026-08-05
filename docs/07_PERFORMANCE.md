# 07. Wydajność i Zapobieganie N+1

## 1. Zasady Query Building
* **Nigdy nie ładuj relacji w pętli Blade / Livewire**: Używaj `modifyQueryUsing(fn($query) => $query->with(['pilot', 'bus', 'customer']))`.
* Obliczenia finansowe w tabelach (np. suma wpłat) wykonuj za pomocą zapytań zagregowanych (`withCount`, `withSum`), a nie pobierając kolekcję modeli.
