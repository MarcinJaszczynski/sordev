# Umowy i wpłaty klienta

## Profile umów

| Profil | Pola | Ledger rozliczenia |
|--------|------|-------------------|
| **Grupowa (zamawiający)** | `contract_type=group`, `payment_scheme=lump_sum\|installments` | 1 wpłata = kwota całkowita |
| **Indywidualna** | `contract_type=individual` | 1 wpłata per umowa (`{kod_imprezy}U001`) |
| **Grupowa (wpłaty osobno)** | `contract_type=group`, `payment_scheme=individual` | N wpłat (cena za osobę), agregat na umowie grupowej |
| **Umowa własna** | `contract_type=custom`, `meta.payment_mode` | wg trybu (całość / transze / per uczestnik / ręcznie) |

## Numeracja

- **Operacyjny** (`operational_number`): widoczny w panelu i w rozliczeniu — `{Event.code}` (grupowa) lub `{Event.code}U001` (indywidualna).
- **TFG** (`contract_number`): `UM/rok/id` — bez zmian dla feedu UFG.

Backfill: `php artisan contracts:backfill-operational-numbers`

## Serwisy

- `App\Services\Contracts\ContractNumberAllocator` — nadawanie numerów operacyjnych
- `App\Services\Contracts\ContractPaymentProfileResolver` — mapowanie profilu
- `App\Services\Contracts\PaymentStrategies\*` — synchronizacja wpłat do `EventSettlementParticipantPayment`
- `App\Services\ContractPaymentSyncService` — fasada sync (delegacja do strategii)

## UI

Impreza → zakładka **Umowy**: kolumny Profil, Postęp wpłat, Numer operacyjny. Dla grupowej z wpłatami osobno: akcja **Synchronizuj wpłaty uczestników**.

## Import bankowy

Dopasowanie po `operational_number` (priorytet) oraz legacy `contract_number`.

## TFG

Feed nadal używa `contract_number`. Decyzja raportowania grupowej z wpłatami indywidualnymi do TFG — osobny etap.
