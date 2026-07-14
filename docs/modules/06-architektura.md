# Architektura techniczna

## Warstwy

```
HTTP / Filament / Livewire
        ↓
   Services (logika biznesowa)
        ↓
   Models + Eloquent
        ↓
   MySQL
```

## Konwencje

| Typ | Lokalizacja |
|-----|-------------|
| Panel admin | `app/Filament/Resources/`, `app/Filament/Pages/` |
| Panel pilot | `app/Filament/Pilot/` |
| Serwisy | `app/Services/` |
| Wsparcie | `app/Support/` |
| Migracje | `database/migrations/` — po pull: `make migrate-check` |
| Testy | `tests/Feature/`, `tests/Unit/` |

## Dev

- `composer dev` — serve + queue + Vite
- Baza: MySQL lokalnie, dump w `deploy/`
- Baseline migracji: `app:sync-migration-baseline` (legacy)

## Integracje

- UFG / TFG — moduł umów (`make ufg-install`)
- KSeF — vendor invoices inbox
- Playwright — `make console-audit`
