# API v1

Wersja: `v1` · Prefix: `/api/v1` · Auth: Laravel Sanctum

---

## Format odpowiedzi

Każdy endpoint zwraca ujednolicony JSON:

```json
{
  "success": true | false,
  "message": "opis operacji",
  "data": { ... } | null,
  "errors": null | { "pole": ["komunikat"] }
}
```

---

## Uwierzytelnianie

### A) Tryb SPA (sesja + ciasteczka)

Stosowany przez panel admina i aplikacje frontendowe działające w tej samej domenie.

```
1. GET  /sanctum/csrf-cookie        → ustaw XSRF-TOKEN
2. POST /api/v1/auth/login          → zaloguj (sesja)
3. Kolejne żądania z withCredentials=true i nagłówkiem X-XSRF-TOKEN
```

Wymagania `.env`:
- `SANCTUM_STATEFUL_DOMAINS` – domeny aplikacji frontendowej (np. `localhost:5173`)
- `CORS_ALLOWED_ORIGINS` – te same domeny z protokołem (np. `http://localhost:5173`)
- `SESSION_SECURE_COOKIE=false` dla lokalnego dev bez HTTPS

### B) Tryb Bearer Token (backend-to-backend / mobilny)

Stosowany przez aplikację Android lub integracje zewnętrzne.

```
1. POST /api/v1/auth/login          → zaloguj użytkownika
2. POST /api/v1/auth/token          → utwórz personal access token
   body: { "name": "android-client", "abilities": ["*"] }
3. Kolejne żądania z nagłówkiem:
   Authorization: Bearer <token>
```

> **Uwaga:** `/api/v1/auth/token` wymaga aktywnej sesji lub tokenu – najpierw zaloguj się przez `/auth/login`.

---

## Endpointy

### Auth

#### `POST /api/v1/auth/login`

**Rate limit:** 10 żądań/minutę (throttle:10,1)

| Pole | Typ | Wymagane | Opis |
|------|-----|----------|------|
| `email` | string/email | ✅ | Adres e-mail użytkownika |
| `password` | string | ✅ | Hasło |
| `remember` | boolean | ❌ | Sesja trwała |

**200 OK:**
```json
{
  "success": true,
  "message": "Zalogowano pomyslnie.",
  "data": {
    "user": { "id": 1, "name": "Jan Kowalski", "email": "jan@example.com", "roles": [...] }
  },
  "errors": null
}
```

**422 – błędne dane:**
```json
{
  "success": false,
  "message": "The email field is required.",
  "data": null,
  "errors": { "email": ["Nieprawidlowy email lub haslo."] }
}
```

---

#### `GET /api/v1/auth/me`

Wymaga: `auth:sanctum`

**200 OK:**
```json
{
  "success": true,
  "message": "OK",
  "data": { "user": { "id": 1, "name": "Jan Kowalski", "email": "jan@example.com", "roles": [...] } },
  "errors": null
}
```

---

#### `POST /api/v1/auth/logout`

Wymaga: `auth:sanctum`  
Unieważnia sesję i bieżący token dostępu.

**200 OK:** `{ "success": true, "message": "Wylogowano pomyslnie.", "data": null, "errors": null }`

---

#### `POST /api/v1/auth/token`

Wymaga: `auth:sanctum`  
Tworzy personal access token (Bearer) dla integracji backend-backend lub klientów mobilnych.

| Pole | Typ | Wymagane | Opis |
|------|-----|----------|------|
| `name` | string (max 255) | ✅ | Nazwa tokenu (identyfikacja klienta) |
| `abilities` | string[] | ❌ | Uprawnienia. Domyślnie `["*"]` |

**201 Created:**
```json
{
  "success": true,
  "message": "Token wygenerowany.",
  "data": {
    "token": "1|abc123def456...",
    "token_type": "Bearer",
    "abilities": ["*"]
  },
  "errors": null
}
```

---

### Eventy (imprezy)

Wszystkie endpointy wymagają `auth:sanctum`.

#### `GET /api/v1/events`

Zwraca paginowaną listę eventów posortowaną od najnowszych.

| Parametr query | Typ | Opis |
|----------------|-----|------|
| `status` | string | Filtr po statusie (np. `confirmed`, `inquiry`, `offer`, `to_settle`, `settled`, `cancelled`) |
| `search` | string (max 255) | Wyszukiwanie po `name`, `client_name`, `code` |
| `per_page` | int (1–100) | Elementów na stronę. Domyślnie 20 |

**200 OK:**
```json
{
  "success": true,
  "message": "OK",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 5,
        "name": "Wycieczka do Krakowa",
        "status": "confirmed",
        "start_date": "2026-06-10",
        "participant_count": 35,
        "assigned_user": { "id": 2, "name": "Anna Nowak" },
        "start_place": { "id": 1, "name": "Warszawa" }
      }
    ],
    "total": 42,
    "per_page": 20,
    "last_page": 3
  },
  "errors": null
}
```

**Statusy eventów:**

| Wartość | Opis |
|---------|------|
| `inquiry` | Zapytanie |
| `offer` | Oferta |
| `provisional_reservation` | Wstępna rezerwacja |
| `confirmed` | Potwierdzona |
| `to_settle` | Do rozliczenia |
| `settled` | Rozliczona |
| `pending_cancellation` | Do anulacji |
| `cancelled` | Anulowana |

---

#### `GET /api/v1/events/{id}`

Zwraca szczegóły eventu z załadowanymi relacjami: `assignedUser`, `startPlace`, `programPoints` (posortowane wg dnia i kolejności), `pricePerPerson`.

**200 OK:**
```json
{
  "success": true,
  "message": "OK",
  "data": {
    "id": 5,
    "name": "Wycieczka do Krakowa",
    "status": "confirmed",
    "start_date": "2026-06-10",
    "end_date": "2026-06-13",
    "duration_days": 4,
    "participant_count": 35,
    "assigned_user": { "id": 2, "name": "Anna Nowak" },
    "start_place": { "id": 1, "name": "Warszawa" },
    "program_points": [...],
    "price_per_person": [...]
  },
  "errors": null
}
```

**404** gdy event nie istnieje.

---

#### `POST /api/v1/events/{id}/recalculate-price`

Przelicza ceny per-person dla eventu na podstawie punktów programu z `include_in_calculation=true` i `active=true`. Usuwa istniejące wpisy `event_price_per_person` i tworzy nowe.

**200 OK:**
```json
{
  "success": true,
  "message": "Ceny zostaly przeliczone.",
  "data": {
    "event_id": 5,
    "price_per_person": [...]
  },
  "errors": null
}
```

---

#### `POST /api/v1/events/{id}/program-points/reorder`

Zmienia kolejność punktów programu danego dnia. Akceptuje tylko punkty należące do wskazanego eventu i dnia.

| Pole | Typ | Wymagane | Opis |
|------|-----|----------|------|
| `day` | int ≥ 1 | ✅ | Numer dnia wycieczki |
| `point_ids` | int[] (min 1, bez duplikatów) | ✅ | Nowe ID punktów w nowej kolejności |

**200 OK:**
```json
{
  "success": true,
  "message": "Kolejnosc punktow zostala zapisana.",
  "data": {
    "event_id": 5,
    "day": 1,
    "program_points": [
      { "id": 12, "day": 1, "order": 1, "name": "Wyjazd" },
      { "id": 9,  "day": 1, "order": 2, "name": "Zwiedzanie" },
      { "id": 5,  "day": 1, "order": 3, "name": "Obiad" }
    ]
  },
  "errors": null
}
```

**422** gdy `point_ids` zawiera ID nienależące do danego dnia/eventu:
```json
{
  "success": false,
  "message": "Lista punktow zawiera rekordy spoza wskazanego dnia lub eventu.",
  "data": null,
  "errors": []
}
```

---

### Taski / Kanban

Wszystkie endpointy wymagają `auth:sanctum`.

#### `GET /api/v1/tasks/board`

Zwraca wszystkie statusy i taski posortowane wg statusu i kolejności (limit 500).

| Parametr query | Typ | Opis |
|----------------|-----|------|
| `assigned_to_me` | boolean | Tylko taski przypisane do zalogowanego użytkownika |
| `status_id` | int | Filtr po konkretnym statusie (musi istnieć w `task_statuses`) |

**200 OK:**
```json
{
  "success": true,
  "message": "OK",
  "data": {
    "statuses": [
      { "id": 1, "name": "To do", "color": "#cccccc", "order": 1 }
    ],
    "tasks": [
      {
        "id": 7,
        "title": "Przygotować umowę",
        "priority": "high",
        "status_id": 1,
        "author": { "id": 1, "name": "Jan" },
        "assignee": { "id": 2, "name": "Anna" },
        "status": { "id": 1, "name": "To do", "color": "#cccccc", "order": 1 }
      }
    ]
  },
  "errors": null
}
```

---

#### `POST /api/v1/tasks/{id}/move`

Przenosi task do innego statusu. Uprawnieni: autor, przypisany użytkownik lub admin/super_admin.

| Pole | Typ | Wymagane | Opis |
|------|-----|----------|------|
| `status_id` | int | ✅ | ID docelowego statusu (musi istnieć) |
| `order` | int ≥ 1 | ❌ | Nowa pozycja w obrębie statusu |

**200 OK:**
```json
{
  "success": true,
  "message": "Zadanie zostalo przeniesione.",
  "data": {
    "id": 7,
    "status_id": 2,
    "status": { "id": 2, "name": "In progress", "color": "#3b82f6", "order": 2 }
  },
  "errors": null
}
```

**403** gdy użytkownik nie jest ani autorem, ani przypisanym:
```json
{
  "success": false,
  "message": "Brak uprawnien do modyfikacji tego zadania.",
  "data": null,
  "errors": []
}
```

---

### Powiadomienia

#### `GET /api/v1/notifications/counts`

Wymaga: `auth:sanctum`  
Zwraca dane paska topbar dla zalogowanego użytkownika (liczniki eventów do działania, tasków, itp.).

**200 OK:**
```json
{
  "success": true,
  "message": "OK",
  "data": { ... },
  "errors": null
}
```

---

## Kody HTTP

| Kod | Znaczenie |
|-----|-----------|
| `200` | Sukces |
| `201` | Zasób utworzony (np. token) |
| `401` | Brak autoryzacji |
| `403` | Brak uprawnień |
| `404` | Zasób nie istnieje |
| `422` | Błąd walidacji |
| `429` | Przekroczony rate limit |
| `500` | Błąd serwera |

---

## Konfiguracja środowiska

```dotenv
# Stateful domains dla SPA
SANCTUM_STATEFUL_DOMAINS=localhost:5173,127.0.0.1:5173

# CORS dla SPA (z protokołem)
CORS_ALLOWED_ORIGINS=http://localhost:5173,http://127.0.0.1:5173

# Lokalny dev
SESSION_SECURE_COOKIE=false
SESSION_DOMAIN=
```

---

## Przykłady requestów

Gotowe przykłady curl i VS Code REST Client: [`docs/PL/API_V1.http`](API_V1.http)

---

## Uwagi migracyjne

Tabela `personal_access_tokens` jest wymagana przez Sanctum. Po wdrożeniu:

```bash
php artisan migrate
php artisan config:clear
php artisan route:clear
```
