# Moduł: Portal klienta

## Zakres

Panel `/portal` dla uczestników wycieczek i opiekunów grup. Analogiczny do panelu pilota, z izolacją danych per użytkownik.

## Kluczowe pliki

- `app/Filament/Client/` — UI panelu
- `app/Providers/Filament/ClientPanelProvider.php`
- `app/Services/ClientAccessService.php` — autoryzacja i widoczność imprez
- `app/Services/ClientPortalProvisioningService.php` — tworzenie dostępu z flow umowy i z biura
- `app/Models/EventPortalAccess.php` — pivot dostępu (user + event + rola)
- `app/Models/ClientInvoiceRequest.php` — wnioski o fakturę od opiekuna

## Role

| Rola Spatie | Portal access `role` | Widok |
|-------------|---------------------|-------|
| `client_participant` | `participant` | program, umowa, harmonogram, **moje wpłaty**, **wniosek o fakturę** |
| `client_guardian` | `guardian` | program, umowa, harmonogram, **wpłaty każdego uczestnika**, **wniosek o fakturę** |

## Zasady

- Uczestnik widzi **tylko swoją** umowę i wpłatę — brak listy innych uczestników
- Opiekun widzi pełną tabelę wpłat grupy (jak w rozliczeniu biura)
- Dostęp per impreza — rekord `event_portal_accesses`, nie globalna flaga na `events`
- Archiwum: pełny dostęp do `end_date + config('portal.full_access_days_after_end')` (domyślnie 90 dni)
- Onboarding: zaproszenie z admina (`ManageEventClientPortal`) **lub** auto po `sign()` / `pay()` w `/umowa/{token}`

## Admin

Sub-strona imprezy: `/{record}/participants/portal` — toolbar zaproszeń, lista dostępów, wnioski o fakturę. Stary URL `/{record}/portal-klienta` przekierowuje na nowy.

Nowy wniosek o fakturę (`status=pending`) trafia do belki powiadomień admina (sekcja **Wnioski o fakturę**) dla ról: `admin`, `super_admin`, `biuro`, `ksiegowosc`. Link prowadzi do portalu klienta danej imprezy.

## URL

- Login: `/portal/login`
- Podgląd biura: dowolny URL panelu + `?preview=1`
