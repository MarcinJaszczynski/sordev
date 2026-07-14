# Moduł: Program imprezy

## Zakres

Punkty programu, sety (podpunkty), dni, godziny, planer 24h, drzewo, finanse per punkt.

## Kluczowe pliki

- `app/Filament/Resources/EventResource/RelationManagers/ProgramPointsRelationManager.php`
- `app/Filament/Resources/EventResource/Pages/EditEventProgram.php`
- `app/Livewire/EventProgramPlanner.php`
- `app/Services/ProgramPointHelper.php`

## Zasady

- Każdy punkt (także podpunkt) ma dostęp do finansów / rozliczenia
- `include_in_calculation`, `include_in_program`, `paid_by` (biuro/pilot)
- Import z szablonu zachowuje hierarchię parent/child
- **Start dnia programu** — per dzień imprezy (`events.program_day_start_times`, domyślnie 08:00); od tej godziny układane są punkty przy zmianie selecta „Start realizacji programu” oraz przy „Przelicz godziny”
