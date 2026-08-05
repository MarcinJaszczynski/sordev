# 09. Konfiguracja i Zasady Pracy z AI Agentami (Cursor / Claude / Roo)

## 1. Złote Zasady dla AI (System Directive)
1. **Nie generuj długich monolitów w jednym pliku**: Dziel kod na DTO, Actions, Filament Resources.
2. **Strict PHP 8.3 & Laravel 11**: Stosuj typowanie argumentów i zwracanych wartości.
3. **Brak logiki w Widokach/Blade**: Cała logika przeliczeń znajduje się w klasach domenowych (Services/Actions).
4. **Zawsze sprawdzaj N+1 Query**: W tabelach Filament upewnij się, że używasz `with(['relation1', 'relation2'])`.
5. **Generuj Testy Pest**: Każda nowa akcja biznesowa musi posiadać odpowiadający test w Pest PHP.
