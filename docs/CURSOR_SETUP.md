# Konfiguracja Cursor (SOR41)

Ustawienia workspace są w repozytorium (`.cursorignore`, `.cursor/rules/`, `.vscode/settings.json`).  
Ten dokument opisuje **jednorazową** konfigurację UI Cursor oraz globalne pliki użytkownika.

## Po `git pull` (checklist)

1. **Cursor Settings → Models**
   - Domyślny model w **Agent** i **Plan**: **Composer 2.5 Fast**
   - **Wyłącz Max Mode** (oszczędność tokenów)

2. **Customize → Rules → User Rules** — wklej:

```
Projekt: Laravel 12 + Filament 3 + Livewire 3 + Pest (SOR41). Nie zakładaj innego stacku.
Oszczędzaj tokeny: grep przed pełnym skanem repo; nie uruchamiaj subagentów dla prostych zadań.
Duże zmiany: najpierw Plan, potem implementacja. Model: Composer 2.5 Fast, bez Max Mode.
Odpowiedzi po polsku gdy użytkownik pisze po polsku. Commity tylko na prośbę.
```

3. **Tryb pracy w czacie Agent**
   - Duże zadania (wiele plików, architektura) → **Plan** (`Shift+Tab`)
   - Małe poprawki (1–2 pliki) → **Agent**

4. **MCP (opcjonalnie)**  
   Skopiuj `.cursor/mcp.json.example` → `.cursor/mcp.json` i uzupełnij tokeny (plik jest w `.gitignore`).

5. **CLI agent (opcjonalnie)**  
   Przy pierwszym użyciu `agent` w terminalu: `/model composer-2.5`  
   Konfiguracja bazowa: `~/.cursor/cli-config.json` (maxMode: false, hints: false).

## Co jest w repozytorium

| Plik | Cel |
|------|-----|
| `.cursorignore` | Mniej indeksu (`vendor`, `storage`, plany, transkrypty) |
| `.cursor/rules/core.mdc` | Stack Laravel/Filament + oszczędność tokenów |
| `.cursor/rules/*.mdc` | Reguły modułowe (tylko przy pasujących plikach) |
| `AGENTS.md` | Pełna mapa projektu dla agenta |
| `.vscode/settings.json` | Pint, Intelephense 8.4, Tailwind, exclude vendor |

## Globalne pliki użytkownika (Linux)

| Plik | Cel |
|------|-----|
| `~/.config/Cursor/User/settings.json` | PHP/Blade formatowanie we wszystkich projektach |
| `~/.cursor/cli-config.json` | Domyślne flagi CLI agenta |

## Weryfikacja

- Nowy chat Agent → model **Composer 2.5 Fast**
- `@codebase` nie podpowiada plików z `vendor/`
- Zapis `.php` → Laravel Pint formatuje plik
- Proste pytanie o jeden plik → agent nie skanuje całego repo subagentem

## Uwaga

Cursor **nie udostępnia** stabilnego klucza `settings.json` na „zawsze startuj w Plan” ani domyślny model w IDE — te preferencje zapisują się po wyborze w UI (Models + tryb w czacie).
