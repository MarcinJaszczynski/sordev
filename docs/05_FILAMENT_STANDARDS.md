# 05. Standardy Tworzenia Modułów w Filament 4

## 1. Standard Wytwarzania Resource'a
Każdy moduł/Resource w Filament musi posiadać:
1. `ListRecords` z zaawansowanymi filtrami, wyszukiwarką po JSON/Relacjach i akcjami masowymi.
2. `CreateRecord` oraz `EditRecord` z wykorzystaniem `Form` ustrukturyzowanego w `Tabs` lub `Section` (Wizard dla skomplikowanych procesów jak kalkulacja wyjazdu).
3. `RelationManagers` dla powiązanych sub-zasobów (np. Punkty programu, Uczestnicy, Wpłaty, Koszty).
4. Auto-save lub wysokie bezawaryjne powiadamianie (Toasts).
