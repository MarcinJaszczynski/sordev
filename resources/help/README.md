# Instrukcja — Centrum pomocy (Help)

Pomoc w aplikacji jest **plikowa**: jeden artykuł = jeden plik Markdown w katalogu panelu. Nie ma bazy danych — zmiany w Git trafiają od razu do UI po odświeżeniu.

## Panele i ścieżki

| Panel | Folder | URL w aplikacji |
|---|---|---|
| Biuro (admin) | `resources/help/admin/` | `/admin/help` |
| Portal klienta | `resources/help/portal/` | `/portal/help` |
| Portal pilota | `resources/help/pilot/` | `/pilot/help` |

Klasa ładująca: `App\Support\Help\HelpCatalog`.

## Jak dodać nowy artykuł

1. Utwórz plik `resources/help/{panel}/{slug}.md` (nazwa pliku bez polskich znaków, kebab-case).
2. Na początku pliku dodaj **YAML frontmatter** (wymagany `title`):

```yaml
---
slug: moj-artykul
title: Krótki tytuł widoczny na liście
summary: Jedno zdanie pod tytułem.
category: Imprezy
tags: slowo1, slowo2, fraza
sort: 50
---
```

3. Pod frontmatterem napisz treść w Markdown (nagłówki `##`, listy, tabele).
4. Otwórz Centrum pomocy w danym panelu i sprawdź artykuł (`?article=moj-artykul`).
5. (Opcjonalnie) podłącz przycisk „Jak to zrobić?” przez `HelpArticleAction::make('moj-artykul', panel: 'admin')`.

### Pola frontmatter

| Pole | Wymagane | Opis |
|---|---|---|
| `title` | tak | Tytuł na liście i w treści |
| `slug` | nie | Domyślnie nazwa pliku bez `.md` |
| `summary` | nie | Krótki opis na karcie listy |
| `category` | nie | Grupowanie na liście |
| `tags` | nie | CSV — wyszukiwanie |
| `sort` | nie | Kolejność (mniejsza = wyżej); domyślnie 100 |

Plik bez `title` lub z błędnym frontmatterem jest **pomijany**.

## Konwencja treści („jak zrobić”)

Każdy artykuł operacyjny powinien mieć sekcje:

1. **Cel** — co użytkownik osiągnie  
2. **Kroki** — numerowana lista z **aktualnymi etykietami UI** (zakładki, przyciski)  
3. **Wynik** — jak wygląda sukces  
4. **Typowe błędy / problemy** — 2–4 punkty  

Dla map ekranów używaj tabeli „Gdzie / Co”.

### Zasady językowe

- Pisz po polsku, krótko, bez jargonów deweloperskich.
- Używaj **dokładnych nazw z UI** (np. **Gotówka pilota**, nie „Gotówka i waluty”; **Obecność**, nie „Frekwencja”; **Płatności**, nie „Moje wpłaty”).
- Nie opisuj starych URL-i (`event-settlements`) jako miejsca pracy — tylko jako ostrzeżenie w „Typowe błędy”.
- Nie wklejaj HTML; Markdown jest czyszczony (`html_input: strip`).

## Aktualne etykiety nawigacji (stan odniesienia)

Przy zmianach UI **najpierw zaktualizuj help**, potem merguj feature.

### Admin — impreza

- Główne: Podsumowanie · Program · Uczestnicy · Operacje · Finanse · Dokumenty  
- Operacje: Zadania · Rezerwacje · Transport · Hotele · Pilot  
- Finanse: Koszty · Wpłaty · Kalkulacja · **Gotówka pilota** · Dok. rozliczenia  
- Dokumenty: Umowy · Pliki · Historia  

### Portal klienta (zakładki wycieczki)

Informacje · Program · Umowa · **Płatności** · Faktura · Uczestnicy · Wpłaty grupy · Kontakt · Świadczenia  

### Portal pilota (zakładki wycieczki)

Informacje · Program · Checklista · **Obecność** · **Rozliczenie** · Hotele · Dokumenty · Kontakt  

## Deep-linki

- Lista: `/admin/help`  
- Artykuł: `/admin/help?article={slug}` (analogicznie `/portal/help`, `/pilot/help`)  
- Szukaj: `?q=fraza`  

Przycisk w Filament:

```php
\App\Filament\Actions\HelpArticleAction::make('rozliczenie-imprezy')
// portal / pilot:
\App\Filament\Actions\HelpArticleAction::make('jak-zaplacic', panel: 'portal')
```

## Checklist przed merge

- [ ] Etykiety UI w tekście = etykiety w kodzie nawigacji  
- [ ] `slug` stabilny (nie zmieniaj bez aktualizacji `HelpArticleAction` i linków)  
- [ ] `sort` nie koliduje z sąsiadami w kategorii  
- [ ] `php artisan test --filter=HelpCatalogTest` przechodzi  

## Testy

`tests/Unit/Support/Help/HelpCatalogTest.php` — katalog admina, wyszukiwanie, markdown, minimalna liczba artykułów portal/pilot.
