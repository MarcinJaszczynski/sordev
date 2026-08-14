# 10. API — kontrakt mobilny (Public / Pilot / Client)

## Cel

Stabilne JSON API pod aplikacje:
1. **Public** — przeglądanie ofert + lead z WWW
2. **Pilot** — aplikacja terenowa (obecność, checklista, rozliczenie, dokumenty, hotele, kontakt)
3. **Client** — portal opiekuna / uczestnika (umowa, płatności, uczestnicy, extras, faktura, kontakt)

Office API (`/api/v1/events|tasks|notifications`) zostaje osobno.

## Zasady

* Prefiks: `/api/v1`
* Envelope: `{ success, message, data, errors }`
* Auth: Sanctum (sesja SPA lub Bearer PAT)
* Abilities: `App\Support\Api\ApiAbilities`
* Audience middleware: `api.pilot` / `api.client` (kontekst Filament)
* Token ability middleware: `api.ability:*` (egzekwowane tylko dla PAT; sesja/TransientToken OK)
* Logika: istniejące Services / Actions / Policies — bez duplikacji SQL
* Write: `pilot:write` / `client:write` + `hasFullAccess` / `assert*MutationsAllowed`

## Token

```http
POST /api/v1/auth/token
{ "name": "pilot-android", "abilities": ["pilot:read", "pilot:write"] }
Authorization: Bearer {token}
```

Domyślne abilities = `ApiAbilities::defaultsFor($user)` (pilot/client dostają też write).

## Endpointy

### Public (`/api/v1/public`)
| Method | Path | Opis |
|--------|------|------|
| GET | `/packages` | katalog ofert |
| GET | `/packages/{id\|slug}` | szczegóły |
| GET | `/regions` | miejsca startowe |
| POST | `/inquiries` | lead WWW (`CreateInquiryFromWebAction`) + honeypot |

### Pilot (`/api/v1/pilot`) — ability `pilot:read` (+ `pilot:write` na mutacje)
| Method | Path | Opis |
|--------|------|------|
| GET | `/trips`, `/trips/{event}`, `/trips/{event}/program` | wycieczki |
| GET/PUT | `/trips/{event}/attendance` | obecność (`MarkAttendanceAction`) |
| GET | `/trips/{event}/checklist` | checklista |
| POST | `/trips/{event}/checklist/{task}/toggle` | toggle |
| GET | `/trips/{event}/documents` | dokumenty + URL PDF API |
| GET | `/trips/{event}/pdf/{pilot\|folder}` | stream PDF |
| GET | `/trips/{event}/hotel-plan` | plan hoteli |
| PUT | `/trips/{event}/hotel-plan/room-numbers` | numery pokoi |
| GET/POST | `/trips/{event}/inquiries` | kontakt do biura |
| GET | `/trips/{event}/settlement` | rozliczenie + zaliczka + cash |
| PUT | `/trips/{event}/settlement/report` | raport (+ `submit_to_office`) |
| POST/PUT/DELETE | `/trips/{event}/settlement/expenses[/{cost}]` | wydatki |
| POST/PUT/DELETE | `/trips/{event}/settlement/exchanges[/{exchange}]` | wymiany |
| PUT | `/trips/{event}/settlement/cash-return` | zwrot gotówki |
| POST/DELETE | `/trips/{event}/settlement/documents[/{document}]` | upload/usuwanie |
| POST | `/trips/{event}/settlement/expenses/sync` | sync planu |

### Client (`/api/v1/client`) — ability `client:read` (+ `client:write`)
| Method | Path | Opis |
|--------|------|------|
| GET | `/trips`, `/trips/{event}`, `/trips/{event}/program` | + readiness na show |
| GET | `/trips/{event}/agreement` (+ `/pdf`) | umowa |
| GET | `/trips/{event}/payments` | saldo / checkout |
| POST | `/trips/{event}/payments/installments/{schedule}/pay-link` | link bramki |
| GET | `/trips/{event}/group-payments` | guardian |
| GET/POST/PUT/DELETE | `/trips/{event}/participants[/{id}]` | guardian CRUD |
| POST | `/trips/{event}/participants/{id}/parent-link` | link rodzica |
| GET/PUT | `/trips/{event}/extras` | świadczenia |
| GET/POST | `/trips/{event}/invoice-requests` | wniosek o fakturę |
| GET/POST | `/trips/{event}/inquiries` | kontakt |

## Poza zakresem (świadomie)
* Zbiórki w autokarze — brak Service (Livewire only); wyciągnąć przed API
* Portal rodzica `/rodzic/{token}` — osobny kanał (nie Sanctum)
* Self-provisioning kont klienta

## OpenAPI
`docs/api/openapi-{public,pilot,client}.yaml`

## Testy
`tests/Feature/Api/V1/*`
