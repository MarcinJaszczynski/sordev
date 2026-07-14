# Prompt: bugfix

---

**Błąd:** [OPIS / KOMUNIKAT KONSOLI / KROKI REPRODUKCJI]

**URL / ekran:** [np. /admin/events/123/program]

Przeczytaj powiązany kod. Znajdź przyczynę, nie objaw.

**Zasady:**
- Najmniejsza możliwa poprawka.
- Nie refaktoryzuj przy okazji.
- Zachowaj kompatybilność z danymi legacy.
- Jeśli UI/Livewire: sprawdź `wire:click` w komórkach tabel, `deferLoading`, Alpine.

**Weryfikacja:**
- [jak odtworzyć fix]
- `composer test` jeśli dotyczy logiki
- `make console-audit` jeśli błąd JS w panelu
