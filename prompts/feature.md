# Prompt: nowa funkcja (backend + Filament)

Skopiuj do Cursora (Agent Mode) po przygotowaniu specyfikacji w ChatGPT.

---

Przeczytaj:
- `docs/CONSTITUTION.md`
- `docs/modules/[NUMER]-[MODUŁ].md`
- powiązane pliki w `app/Services/` i `app/Filament/`

**Cel:** [OPIS FUNKCJI W 2–3 ZDANIACH]

**Wymagania:**
- [ ] [wymaganie 1]
- [ ] [wymaganie 2]
- [ ] [wymaganie 3]

**Ograniczenia:**
- Nie twórz nowego modułu od zera — rozszerz istniejącą architekturę.
- Nie usuwaj istniejących funkcji.
- Kod produkcyjny, minimalny diff.
- Finanse: `MoneyFormatter`, audyt, jawna waluta.
- Testy PHPUnit dla logiki serwisu.

**Po implementacji:**
- `composer test --filter=[TestClass]`
- krótki opis co zmieniono i jak ręcznie sprawdzić w `/admin`
