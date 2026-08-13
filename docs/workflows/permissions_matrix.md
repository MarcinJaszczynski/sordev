# Uprawnienia: biuro vs admin

Skrót „kto może co” w codziennej pracy. Role: `super_admin`, `admin`, `biuro`, uprawnienia Shield (np. `edit event_template`), `pilot`, klient.

## Szablony imprez

| Akcja | Admin / super_admin | Biuro (`edit event_template`) | Tylko program (`edit event_template_program`) | Pilot |
|---|---|---|---|---|
| Podgląd szablonu | tak | przy `view event_template` | — | nie |
| Edycja danych / cen / SEO | tak | tak | nie | nie |
| Edycja programu szablonu | tak | tak | tak | nie |
| **Klonuj szablon** | tak | tak (wymaga edycji szablonu) | nie | nie |
| Generuj imprezę ze szablonu | tak | tak | nie | nie |

Źródło: `AuthorizesEventTemplatePages` — klon jest na stronie edycji / liście szablonów z pełną edycją.

## Imprezy i finanse

| Akcja | Admin / super_admin | Biuro (dostęp do imprezy) | Pilot (bez dostępu biurowego) |
|---|---|---|---|
| Podgląd / edycja imprezy | tak | tak (wg widoczności) | tylko przypisane |
| **manageFinance** (wpłaty kosztów, plan kosztów, dokumenty rozliczenia) | tak | tak (jeśli może edytować imprezę) | **nie** |
| Rozliczenie pilota (osobny zakres) | tak | tak | tak (własny zakres `EventSettlementPolicy`) |
| Portal klienta | wg roli | tak (office) | nie |

Źródło: `EventPolicy::manageFinance`, `EventSettlementPolicy`.

## Zadania systemowe przy statusie

Po zmianie statusu zadania biurowe trafiają do użytkowników z rolami `admin`, `super_admin`, `biuro` (`OfficeTaskRecipients`).

## Powiadomienia do klienta

Przy statusie **Oferta** i **Potwierdzona**:

- SMS na `client_phone` (treść PL),
- e-mail na `client_email` (`EventStatusChangedMail`).

## Zasada praktyczna

- **Biuro** prowadzi ścieżkę oferty, operacje i finanse imprezy.
- **Admin** ma to samo + administrację katalogów / użytkowników / Shield.
- **Pilot** nie klonuje szablonów i nie zapisuje krytycznych finansów biurowych; ma własny panel rozliczenia.
