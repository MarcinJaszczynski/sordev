# ActivateEventTemplatesSeeder

Seeder służy do masowego uaktywnienia wybranych szablonów (`event_templates`) oraz uzupełnienia brakujących pól:
- `is_active` ustawione na `1`
- `slug` generowany jeśli pusty (unikalny, na bazie nazwy; w razie konfliktu dodawane sufiksy `-1`, `-2`, ...)
- `duration_days` wyznaczony jeśli brak (próba odczytu z kolumny `length_days` jeśli istnieje, następnie maksymalny `day` z pivotu programu; fallback: `1` dzień)

## Plik
`database/seeders/ActivateEventTemplatesSeeder.php`

## Konfiguracja
Wewnątrz seeder-a znajduje się tablica:
```php
$templateIds = [155];
```
Zmień/rozszerz według potrzeb (np. `[155, 160, 161]`).

## Uruchomienie
```bash
php artisan db:seed --class=Database\\Seeders\\ActivateEventTemplatesSeeder
```
Jeżeli korzystasz z przestrzeni nazw domyślnej Laravel 10+, może być też:
```bash
php artisan db:seed --class=ActivateEventTemplatesSeeder
```

## Bezpieczeństwo / Idempotencja
Seeder jest idempotentny w zakresie:
- Ponowne uruchomienie nie nadpisze istniejącego niepustego `slug`.
- `duration_days` zostanie ustawione tylko jeśli było puste / 0.
- `updated_at` jest aktualizowane przy każdej aktualizacji rekordu.

## Diagnostyka
Po uruchomieniu w konsoli pojawią się komunikaty:
```
Updated template ID 155
ActivateEventTemplatesSeeder completed.
```

## Rekomendacje Dalszych Kroków
1. Dodać test Feature sprawdzający, że slug generuje się poprawnie i nie zachodzi konflikt.
2. Rozszerzyć seeder o opcjonalne wymuszenie regeneracji slugów (flaga środowiskowa).
3. Jeśli planujesz hurtowe aktywacje – rozważyć migrację / job asynchroniczny z logowaniem do osobnej tabeli.

---
Autor: automatyczna implementacja pomocnicza
