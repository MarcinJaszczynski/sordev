# Checklist: nowa funkcja w SOR41

Użyj przed mergem / wdrożeniem większej zmiany.

## Specyfikacja
- [ ] Zgodność z `docs/CONSTITUTION.md`
- [ ] Moduł opisany lub zaktualizowany w `docs/modules/`
- [ ] Prompt z `prompts/feature.md` lub `architecture.md` był użyty

## Kod
- [ ] Logika w serwisie, nie w widoku
- [ ] Finanse: waluta, `MoneyFormatter`, brak hard-delete
- [ ] Filament: uprawnienia, walidacja
- [ ] Brak sekretów w diffie

## Testy
- [ ] `composer test` (odpowiednie filtry)
- [ ] `make smoke-check` po zmianach HTTP
- [ ] `make console-audit` po zmianach UI panelu
- [ ] Wpis w `docs/MANUAL_TESTS.md` jeśli nowy flow ręczny

## Git
- [ ] Commit na stabilnym branchu
- [ ] Opis commita: dlaczego, nie tylko co
