# SOR41 / bprafa — system projektowy panelu (admin + pilot)

Dokument opisuje spójny, nowoczesny i responsywny wygląd backendu (Filament 3). Implementacja: `admin-readability-styles.blade.php`, kolory w `AdminPanelProvider` / `PilotPanelProvider`, ikony grup w `FilamentNavigation`.

Inspiracja: **Stripe**, **Linear**, **Notion** — czytelny dashboard biurowy, nie landing marketingowy.

---

## 1. Tożsamość marki

| Element | Wartość |
|---------|---------|
| Nazwa panelu admin | **bprafa** |
| Panel pilota | **Portal pilota** |
| Charakter | Biuro turystyczne — profesjonalnie, ciepło, zaufanie finansowe |
| Ton wizualny | Jasny motyw, wysoki kontrast, duży spacing, subtelne cienie |

---

## 2. Paleta kolorów

### 2.1 Brand (admin)

| Token CSS | Hex | Użycie |
|-----------|-----|--------|
| `--sor-brand-primary` | `#B45309` | CTA, aktywna nawigacja, focus ring |
| `--sor-brand-primary-hover` | `#92400E` | Hover przycisków primary |
| `--sor-brand-primary-soft` | `#FFFBEB` | Tło badge / highlight |
| `--sor-brand-secondary` | `#1E3A5F` | Nagłówki sekcji, sidebar brand |

### 2.2 Neutralne powierzchnie

| Token | Hex | Użycie |
|-------|-----|--------|
| `--sor-surface` | `#F8FAFC` | Tło strony, sidebar |
| `--sor-surface-elevated` | `#FFFFFF` | Karty, modale, tabele |
| `--sor-border` | `#E2E8F0` | Obramowania |
| `--sor-border-strong` | `#CBD5E1` | Nagłówki tabel, separatory dni |
| `--sor-text` | `#0F172A` | Tekst główny |
| `--sor-text-muted` | `#64748B` | Helper text, metadane |

### 2.3 Semantyka (statusy, finanse)

| Token | Hex | Użycie |
|-------|-----|--------|
| `--sor-success` | `#059669` | Opłacone, aktywne, OK |
| `--sor-warning` | `#D97706` | Zaliczka, oczekujące |
| `--sor-danger` | `#DC2626` | Błąd, przeterminowane |
| `--sor-info` | `#0284C7` | Informacja, linki pomocnicze |

### 2.4 Moduły nawigacji (akcent grup menu)

| Grupa | Kolor | Ikona Heroicon |
|-------|-------|----------------|
| Obsługa imprez | `#2563EB` | `map` |
| Finanse | `#059669` | `banknotes` |
| Zarządzanie | `#7C3AED` | `chart-bar-square` |
| Kontakty | `#0891B2` | `users` |
| Słowniki | `#64748B` | `book-open` |
| System | `#475569` | `cog-6-tooth` |

**Pilot**

| Grupa | Kolor | Ikona |
|-------|-------|-------|
| Moje wycieczki | `#0D9488` | `paper-airplane` |
| Rozliczenia | `#059669` | `wallet` |

### 2.5 Panel pilota — primary

Teal `#0D9488` (Filament `Color::Teal`) — odróżnia portal pilota od biura, zachowuje spójność komponentów.

---

## 3. Typografia

| Element | Font | Rozmiar (desktop) | Waga |
|---------|------|-------------------|------|
| UI | **Inter**, system-ui fallback | `--admin-root-font` (16px @ xl) | 400–700 |
| Nagłówki stron | Inter | `--admin-heading-size` | 700 |
| Etykiety formularzy | Inter | `--admin-label-size` | 700 |
| Tabele — nagłówek | Inter | `--admin-table-header-size` | 700, uppercase tracking |
| Tabele — komórka | Inter | `--admin-table-cell-size` | 400–600 |
| Kwoty | Inter tabular-nums | jak komórka | 600, klasa `.money-nowrap` |

Skala responsywna jest w CSS (`--admin-*` w `:root`) — powiększa się od 640px do 4K.

---

## 4. Spacing, zaokrąglenia, cienie

| Token | Wartość |
|-------|---------|
| `--sor-radius-sm` | `0.375rem` (6px) — badge, chip |
| `--sor-radius-md` | `0.5rem` (8px) — input, przycisk |
| `--sor-radius-lg` | `0.75rem` (12px) — karta, sekcja |
| `--sor-radius-xl` | `1rem` (16px) — modal, duże panele |
| `--sor-shadow-sm` | delikatny — wiersze hover |
| `--sor-shadow-md` | karty, dropdown |
| `--sor-shadow-lg` | modal, set preview |
| `--sor-transition` | `180ms cubic-bezier(0.4, 0, 0.2, 1)` |

Padding strony: `clamp(0.75rem, 2vw, 1.5rem)` — już w `.fi-page-content`.

---

## 5. Komponenty Filament

### 5.1 Sidebar

- Tło: `--sor-surface`, obramowanie prawe `--sor-border`
- Grupa menu: kolorowy pasek po lewej (`sor-nav-group--*`)
- Aktywny element: tło `--sor-brand-primary-soft`, tekst `--sor-brand-primary`
Grupy menu: kolorowy pasek po lewej (`sor-nav-group--*`). Ikony tylko na pozycjach menu (Filament nie pozwala na ikony grupy i pozycji jednocześnie).

### 5.2 Topbar

- Sticky od `1280px`, tło białe, cień `--sor-shadow-sm`
- Powiadomienia: panel `.topbar-notification-panel` — elevated surface

### 5.3 Sekcje i formularze

- `.fi-section`: zaokrąglenie `--sor-radius-lg`, obramowanie `--sor-border`
- Focus inputów: ring w kolorze primary (amber)
- Pola: min. wysokość dotykowa `--admin-touch-min` (44px) na mobile

### 5.4 Tabele

- Kontener: zaokrąglony, obramowanie, overflow-x auto na `<768px`
- Nagłówki: uppercase, muted, letter-spacing
- Wiersze: hover `--sor-surface`, zebra opcjonalnie przez Filament `striped()`
- Akcje w wierszu: `x-on:click.stop` (nie `wire:click`)

### 5.5 Badge / statusy finansowe

| Klasa | Kolor |
|-------|-------|
| `.admin-finance-ok` | success |
| `.admin-finance-warn` | danger |
| `.admin-finance-muted` | muted |

### 5.6 Program imprezy (custom)

- Set: badge niebieski, rozwijany
- Dzień: banner `.epp-day-banner`
- Workflow: `.workflow-module-nav-item`

---

## 6. Ikony — konwencje

- **Heroicons v2 outline** w nawigacji i akcjach tabeli
- **Emoji** tylko w metadanych punktu programu (🚌 🏨) — nie w menu głównym
- Rozmiar ikony sidebar: `1.15rem`
- Akcje wiersza: ikona + etykieta na desktop; na mobile — pełna szerokość przycisku

Przydatne mapowania:

| Akcja | Ikona |
|-------|-------|
| Finanse punktu | `banknotes` |
| Rezerwacja | `ticket` |
| Zadanie | `clipboard-document-list` |
| PDF / eksport | `document-arrow-down` |
| Planer | `clock` |
| Import | `arrow-up-tray` |

---

## 7. Responsywność

| Breakpoint | Zachowanie |
|------------|------------|
| `<640px` | Sidebar jako drawer, tabele scroll X, przyciski min 44px |
| `640–1023px` | 2-kolumnowe formularze → 1 kolumna |
| `1024–1279px` | Pełny sidebar, sticky topbar wyłączony |
| `≥1280px` | Sticky topbar, większa typografia |
| `≥1536px` | Szersze tabele, więcej kolumn widocznych |

Panel pilota: przyciski `.pilot-touch-btn` i `.pilot-field` — pełna szerokość na mobile.

Testy: `make console-audit`, `docs/MANUAL_TESTS.md` (sekcja responsywności).

---

## 8. Dostępność

- Kontrast tekstu ≥ WCAG AA na jasnym tle
- Focus ring widoczny (nie usuwaj outline bez zamiennika)
- `aria-expanded` na rozwijanych setach programu
- Etykiety formularzy zawsze powiązane z polami (Filament domyślnie)

---

## 9. Czego unikać

- Hardcoded `PLN` w UI — `MoneyFormatter`
- Ciemny motyw (wyłączony globalnie — `darkMode isForced: false` + light)
- Agresywne animacje (>250ms, bounce)
- `@apply` w plikach Blade ze stylami inline — trzymaj tokeny w `:root`
- Duplikowanie kolorów hex poza tokenami

---

## 10. Pliki implementacji

| Plik | Rola |
|------|------|
| `resources/views/filament/components/admin-readability-styles.blade.php` | Tokeny + override Filament |
| `app/Providers/Filament/AdminPanelProvider.php` | Kolory Filament, font, hook CSS |
| `app/Providers/Filament/PilotPanelProvider.php` | Wariant pilota |
| `app/Support/FilamentNavigation.php` | Ikony i klasy grup admin |
| `app/Support/PilotNavigation.php` | Ikony i klasy grup pilota |
| `docs/modules/07-ui.md` | Skrót dla developerów |

---

## 11. Roadmap (kolejne kroki)

1. ~~Logo SVG w `->brandLogo()`~~ — `public/images/bprafa-logo.svg`
2. ~~Empty state per moduł~~ — komponent `x-filament.components.sor-empty-state`
3. ~~Podgląd UI~~ — `/admin/design-system-preview-page` (System → Podgląd UI)
4. Dark mode opcjonalny (po testach kontrastu finansów)
5. Eksport do Figmy (opcjonalnie)
