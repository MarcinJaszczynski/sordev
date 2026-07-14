# Środowisko pracy — Cursor + AI dla SOR41

**Skonfigurowane pod ten projekt** — uruchom ponownie: `make cursor-setup`.

| Plik | Po co |
|------|--------|
| [`AGENTS.md`](../AGENTS.md) | Główna instrukcja dla agenta Cursor |
| [`PROJECT_MAP.md`](PROJECT_MAP.md) | Mapa plików i modułów |
| [`CONSTITUTION.md`](CONSTITUTION.md) | Zasady biznesowe |

Przewodnik krok po kroku: gdzie co wpisać w Cursorze.

## 1. Co już jest w tym repozytorium

```
sor41/
├── app/                    # Backend Laravel (modele, serwisy, Filament)
├── resources/              # Blade, JS, CSS (Vite)
├── tests/                  # PHPUnit
├── docs/                   # Dokumentacja projektu
│   ├── DEV.md              # Uruchomienie lokalne (make setup, composer dev)
│   ├── MANUAL_TESTS.md     # Checklisty testów ręcznych
│   ├── CONSTITUTION.md     # Konstytucja projektu (czytaj przed większymi zmianami)
│   ├── WORKSPACE.md        # Ten plik
│   └── modules/            # Opisy modułów biznesowych
├── prompts/                # Gotowe szablony poleceń dla AI
├── .cursor/rules/          # Reguły Cursora (czytane automatycznie)
├── .vscode/                # Rekomendowane rozszerzenia
└── scripts/                # Skrypty (smoke, console-audit, setup)
```

## 2. Cursor — gdzie wpisać reguły

### Project Rules (najważniejsze)

Reguły są w katalogu **`.cursor/rules/`** jako pliki `.mdc`. Cursor czyta je automatycznie przy każdej sesji w tym projekcie.

| Plik | Kiedy działa |
|------|----------------|
| `core.mdc` | Zawsze (`alwaysApply: true`) |
| `backend-php.mdc` | Przy plikach `app/`, `database/`, `routes/`, `tests/` |
| `filament-ui.mdc` | Przy `app/Filament/`, widokach Filament |
| `finance.mdc` | Przy finansach, rozliczeniach, umowach |
| `design.mdc` | Przy Blade/CSS/JS frontu i panelu |
| `security.mdc` | Przy auth, uprawnieniach, płatnościach |

**Edycja w UI:** `Cursor Settings` → `Rules` → zobaczysz reguły projektu z `.cursor/rules/`.

**Nie zostawiaj pustych User Rules** — tam możesz dodać tylko osobiste preferencje (język odpowiedzi, styl commitów). Logika biznesowa należy do `.cursor/rules/` i `docs/CONSTITUTION.md`.

### User Rules vs Project Rules

| Miejsce | Co tam dawać |
|---------|----------------|
| **Cursor → Settings → Rules → User** | „Odpowiadaj po polsku”, „Nie commituj bez prośby” |
| **`.cursor/rules/*.mdc`** | Standardy kodu, finanse, Filament, bezpieczeństwo |
| **`docs/CONSTITUTION.md`** | Architektura i zasady biznesowe na lata |

## 3. MCP — podłączenie narzędzi

MCP pozwala AI czytać GitHub, bazę, przeglądarkę itd.

### Gdzie konfigurować

1. **Cursor → Settings → MCP** (lub `~/.cursor/mcp.json` globalnie)
2. W projekcie jest szablon: **`.cursor/mcp.json.example`** — skopiuj do `~/.cursor/mcp.json` i uzupełnij tokeny

### Rekomendowany zestaw dla SOR41

| MCP | Po co |
|-----|--------|
| **Filesystem** | Domyślnie — AI widzi pliki projektu |
| **GitHub** | PR, issues, diffy, commity |
| **PostgreSQL / MySQL** | Zapytania, analiza schematu (u nas: **MySQL** na `127.0.0.1:3306`) |
| **Playwright** | Audyt konsoli (`make console-audit`) |
| **Browser** | Podgląd panelu `/admin`, `/pilot` |
| **Figma** | Jeśli projektujesz UI w Figmie |

**Uwaga:** tokeny i hasła **nigdy** nie commituj do repo. Tylko `.example`.

## 4. Rozszerzenia VS Code / Cursor

Otwórz paletę (`Ctrl+Shift+P`) → **Extensions: Show Recommended Extensions** — Cursor zaproponuje listę z `.vscode/extensions.json`.

Zainstaluj przynajmniej: **PHP Intelephense**, **Laravel Pint**, **Blade**, **ESLint**, **Prettier**, **GitLens**, **Error Lens**.

## 4a. Model i Run Mode (Cursor)

W planie Cursor Pro często jest **Gemini 2.5 Flash**, nie Pro — to w porządku.

| Ustawienie | Gdzie | Wartość |
|------------|--------|---------|
| Model | **Cursor Settings → Models** | Włącz **Gemini 2.5 Flash**, wyłącz **Auto** |
| Domyślny do kodu | Panel **Agent** → dropdown u góry | **Gemini 2.5 Flash** (+ „default for Agent” jeśli jest) |
| Bezpieczeństwo | **Cursor Settings → Agents → Run Mode** | **Ask** (nie „Run Everything”) |

**Flash vs Pro:** Flash w Cursorze na codzienne kodowanie SOR41 wystarczy. Trudniejszą architekturę i review rób w **ChatGPT** (prompt `prompts/architecture.md`), nie musisz mieć Pro w Cursorze.

## 5. Podział modeli AI (ekonomiczny)

| Model | ~% pracy | Zadania |
|-------|----------|---------|
| **Gemini 2.5 Flash** (w Cursorze) | 70% | Implementacja, refaktoryzacja, backend, debug — duży kontekst |
| **Gemini 2.5 Pro** (jeśli masz w planie) | — | Trudniejsza architektura w Cursorze zamiast Flash |
| **ChatGPT Plus** | 20% | Architektura, UX, specyfikacje, code review, prompty |
| **Claude** (opcjonalnie) | 10% | Dopracowanie UI, komponenty, Tailwind/Blade |

### Workflow na większą funkcję

```
1. ChatGPT  → spec + prompt (szablon: prompts/feature.md)
2. Gemini Flash → implementacja w Cursorze (Agent, Run Mode: Ask)
3. ChatGPT  → review bezpieczeństwa i UX
4. Cursor   → poprawki + php artisan test + make smoke-check
5. git commit → stabilny punkt powrotu
```

## 6. Jak rozmawiać z AI w tym projekcie

**Słabo:**
> Zrób panel finansów.

**Dobrze:**
> Przeczytaj docs/CONSTITUTION.md i docs/modules/03-finanse.md.
> Zaimplementuj filtr waluty na liście imprez zgodnie z istniejącym EventListFinanceColumn.
> Nie usuwaj istniejących funkcji. Kod produkcyjny. Testy tam gdzie ma sens.

Szablony w **`prompts/`** — kopiuj i wklejaj, uzupełniaj `[MODUŁ]` i `[CEL]`.

## 7. Git — gałęzie

```
main          → produkcja
develop       → integracja (jeśli używasz)
feature/...   → jedna funkcja na branch
```

Przed większym refaktorem: `git checkout -b feature/nazwa` + commit po każdym działającym kroku.

## 8. Codzienny start

```bash
cd /ścieżka/do/sor41
composer dev                    # serwer + queue + Vite
# Panel: http://127.0.0.1:8000/admin
```

Po `git pull`: `make migrate-check`

Przed PR: `composer test` i `make smoke-check` (opcjonalnie `make console-audit`).

## 9. Checklist przed wdrożeniem funkcji

- [ ] Zgodność z `docs/CONSTITUTION.md`
- [ ] Finanse: audyt, soft delete, waluta jawna
- [ ] Filament: uprawnienia (Shield), walidacja formularzy
- [ ] Testy PHPUnit dla logiki biznesowej
- [ ] `docs/MANUAL_TESTS.md` — wpis jeśli nowy flow UI
- [ ] Brak sekretów w diffie (`.env`, tokeny MCP)

## 10. Dalsze kroki (opcjonalnie)

1. Uzupełniaj `docs/modules/` w miarę rozwoju systemu
2. Dodawaj wpisy do `docs/CONSTITUTION.md` przy decyzjach architektonicznych
3. Rozszerzaj `prompts/` o sprawdzone prompty z realnych zadań
4. Podłącz MCP GitHub + MySQL gdy pracujesz intensywnie z AI
