# Prompt: baza danych / migracja

---

**Cel:** [np. nowa kolumna, indeks, tabela]

**Kontekst:** Projekt ma legacy dump MySQL — po `git pull` używamy `make migrate-check` i baseline (`app:sync-migration-baseline`).

**Zasady:**
- Migracja odwracalna (`down()`).
- `Schema::hasColumn` / `hasTable` jeśli migracja może być częściowo zastosowana.
- Nie niszcz danych finansowych.
- Indeksy na FK i kolumnach filtrowania list.

**Dostarcz:**
1. Plik migracji Laravel
2. Aktualizacja modelu (`$fillable`, `$casts`)
3. Czy wymaga wpisu w seederze / komendzie baseline

Baza dev: patrz `docs/DEV.md` (MySQL `host378742_sor26`).
