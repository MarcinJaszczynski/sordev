## Przeliczanie i zapisywanie cen (rewrite_prices.php)

Skrypt: `php scripts/rewrite_prices.php`

Cel: Ujednolicone przeliczenie cen dla wszystkich szablonów wycieczek (`event_templates`) dla KAŻDEGO miejsca startowego oraz zapis/aktualizacja rekordów w tabeli `event_template_price_per_person` przy użyciu jednego silnika kalkulacyjnego.

### Logika
1. Pobierane są wszystkie szablony oraz wszystkie miejsca z `places` gdzie `starting_place = 1`.
2. Tworzony jest iloczyn kartezjański (template × start_place) oraz dołączane istniejące pary z tabeli `event_template_starting_place_availability`.
3. Dla każdej pary wywoływany jest `EventTemplateCalculationEngine::calculateDetailed()`.
4. Wynik dla każdej ilości uczestników (qty) zapisywany jest do `event_template_price_per_person` metodą manualnego upsertu (jeśli rekord istnieje – update, inaczej insert).
5. Zaokrąglanie wykonywane jest centralnie przez `App\Services\PriceRoundingService`:
   - PLN: w górę do najbliższych 5 (np. 416.01 → 420)
   - Inne waluty: w górę do najbliższych 10 jednostek (np. 73.12 → 80)

### Rounding – implementacja
Plik: `app/Services/PriceRoundingService.php`
```php
PriceRoundingService::roundPerPerson($raw, $currencyCode);
```

### Przykład sanity (Template 31, start_place=1)
Surowa cena za osobę ok. 416.54 PLN -> zapis w bazie: 420 PLN.

### Backup
Przed zapisem tworzony jest backup pliku SQLite: `database.sqlite.bak.YYYYmmdd_HHMMSS`.

### Raport
Po zakończeniu generowany jest plik JSON: `scripts/rewrite_prices_report_YYYYmmdd_HHMMSS.json` zawierający:
```json
{
  "summary": {"pairs": <liczba_par>, "saved": <liczba_zapisanych_wierszy>},
  "problematic": [ ... ]
}
```

### Uruchomienia przykładowe
Pełne przeliczenie:
```
php scripts/rewrite_prices.php
```

Tryb obserwacji (bez zapisu) – niezaimplementowany (TODO: dodać flagę --dry ponownie jeśli potrzebna), aktualnie zapis zawsze następuje.

Ograniczenie do wybranej liczby par (historyczna flaga `--limit`) – obecnie brak aktywnej obsługi przez parser opcji (TODO: reintrodukcja jeśli wymagane).

### TODO / dalsze usprawnienia
- Parametr `--only=ID` (aktualnie usunięty przy refaktorze) – ponownie dodać jeśli konieczne.
- Parametr `--dry` – jeśli potrzebny podgląd bez zapisu.
- Cache kursów walut aby uniknąć wielu zapytań.
- Eager loading relacji używanych w silniku (punkty programu, pokoje hotelowe) dla redukcji N+1.

### Bezpieczeństwo
Skrypt wykonuje kopię bazy przed startem. W środowisku produkcyjnym zalecane wykonanie dump-a oraz uruchomienie poza godzinami szczytu.

---
Ostatnia aktualizacja: 2025-09-15