# Checklista: UX / UI

## Wymagania przed akceptacją kodu

- [ ] Zasada 2 kliknięć, responsywność, obsługa błędów, empty states.
- [ ] Przetestowano w środowisku staging.
- [ ] Brak regresji wydajnościowej.

## Kanon IA imprezy (Admin)

- [ ] Primary nav ≤ 6: Dane, Program, Uczestnicy, Operacje, Finanse, Dokumenty.
- [ ] Nested huby mają module nav + breadcrumbs `Imprezy › {nazwa} › Moduł › Sekcja`.
- [ ] H1 = aktywna sekcja nested (nie powtórzenie primary).
- [ ] Finanse: Koszty / Wpłaty / Kalkulacja / Gotówka / Dok. rozliczenia — bez osobnego UI settlement.
- [ ] Empty state gdy brak settlementu + jawne „Utwórz rozliczenie” (bez mutacji na samym GET). Impreza z szablonu ma draft + koszty planu od razu.

## Portale

- [ ] Client / Pilot: trip module nav, forced light, banner podglądu biura.
- [ ] CTA płatności: spójny label `PaymentCta::label()` (Harmonogram, Moje wpłaty, rodzic, signed).

## Finanse globalne

- [ ] Sidebar: skróty (pulpit, skrzynki, rejestry); narzędzia głównie z module nav / pulpitu.
- [ ] „Rejestr wpłat” (global) ≠ „Wpłaty” (impreza) ≠ „Moje wpłaty” (portal).
