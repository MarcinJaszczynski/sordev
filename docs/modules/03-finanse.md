# Moduł: Finanse

## Zakres

Kalkulacja cen, rozliczenia biura, wpłaty klientów, umowy/kontrakty, faktury KSeF, sterta płatności, raporty.

## Kluczowe pliki

- `app/Services/UnifiedPriceCalculator.php` — silnik cen szablonu
- `app/Filament/Resources/EventResource/Widgets/EventPriceTable.php` — kalkulacja imprezy
- `app/Models/EventSettlement.php`, `EventSettlementCost.php`
- `app/Models/EventSettlementParticipantPaymentEntry.php` — pojedyncza wpłata w historii uczestnika
- `app/Services/ParticipantPaymentLedgerService.php` — dodawanie/usuwanie wpłat + synchronizacja sumy na nagłówku
- `app/Support/MoneyFormatter.php` — format kwot z walutą
- `app/Support/EventListFinanceColumn.php` — podgląd finansów na liście imprez

## Zasady

- Suma końcowa per waluta z `detailedCalculations[$qty][$currencyCode]`
- Rozliczenie aktywne: statusy `draft`, `active`, `pilot_settled`
- KSeF → punkt programu: `VendorInvoiceProgramPointSync`
- **Semafor kosztów** (`SettlementPaymentHealthService`): zielony = plan zgadza się z wpłatami, niebieski = w terminie, pomarańczowy = po terminie, czerwony = brakuje
- **Podział biuro/pilot** (`SettlementPayerBreakdownService`): agregaty plan/wpłacono/brakuje per `paid_by`
- **Wpłaty klientów** (`ParticipantPaymentBalanceService`): salda per uczestnik/umowa z harmonogramem rat (kumulatywnie)
- **Ledger wpłat w rozliczeniu** (`ParticipantPaymentsLedger` + `ParticipantPaymentLedgerService`): jeden wiersz na uczestnika, podwiersze z datą i kwotą; źródła `manual|bank_import|online`; suma `paid_amount_pln` wyliczana z wpisów; UI: impreza → **Uczestnicy → Wpłaty** (zakres wyłącznie bieżącej imprezy — `eventId` w ledgerze)
- **Import wpłat bankowych**: globalny panel **Finanse → Import wpłat bankowych** (`BankPaymentImportPage`) — wszystkie imprezy; w kontekście imprezy **Uczestnicy → Wpłaty** — panel `EventBankPaymentImportPanel` (filtr `BankPaymentImportEventScope`, ukrywa wpłaty innych imprez)
- Raport rozliczenia: podsumowanie imprezy → „Eksport raportu” (Excel, `EventSettlementReportExport`)

## Waluty

- Symbol waluty w kalkulacji: klucze `PLN`, `EUR`, itd.
- Kurs: 5 miejsc po przecinku w wymianach pilota
- Lista imprez: filtr **Waluta (Finanse)** w tabeli
