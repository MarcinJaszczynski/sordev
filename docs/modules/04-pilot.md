# Moduł: Pilot

## Zakres

Panel pilota, zaliczki, wydatki, gotówka, rozliczenie końcowe, checklisty, program w terenie.

## Kluczowe pliki

- `app/Filament/Pilot/`
- `app/Services/PilotAdvanceService.php`
- `app/Services/PilotSettlementService.php`
- `app/Models/PilotCurrencyExchange.php`

## Zasady

- `shared_with_pilot` — impreza widoczna dopiero po udostępnieniu **przypisanemu** pilotowi (`assigned_to`); inni piloci jej nie widzą (także admin z rolą pilota w panelu `/pilot`)
- Zaliczka planowana vs wypłacona (kwota, waluta, komentarz, kto zatwierdził)
- Wydatki: numer faktury, opcjonalnie paragon
