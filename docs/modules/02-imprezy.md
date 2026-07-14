# Moduł: Imprezy

## Zakres

Tworzenie imprezy ze szablonu lub „czystej”, statusy, uczestnicy, terminy, gotowość operacyjna, workflow podstron (program, transport, hotel, pilot, finanse).

## Kluczowe pliki

- `app/Models/Event.php`
- `app/Filament/Resources/EventResource.php`
- `app/Filament/Resources/EventResource/Pages/*`
- Lista: `ListEvents.php`, kolumna finansów: `app/Support/EventListFinanceColumn.php`

## Statusy

Używaj `Event::getStatusOptions()` i `changeStatus()` — nie zmieniaj statusu „ręcznie” bez logiki modelu.

## Uwagi dla AI

- Sub-nawigacja imprezy: `EventResource::getRecordSubNavigation()`
- Nie usuwaj pól legacy bez sprawdzenia `Schema::hasColumn` / migracji baseline
