# Naprawa błędu TraitCompressesImages - Brak metody afterSave

## Problem
Trait `CompressesImages` próbował wywołać `parent::afterSave()` na klasach, które nie miały tej metody, co prowadziło do błędów runtime.

## Przyczyna
- Klasa `EditEventTemplate` ma własną metodę `afterSave()` i działa poprawnie
- Klasa `EditEventTemplateProgramPoint` nie ma tej metody, gdyż bazowa klasa Filament `EditRecord` jej nie posiada
- Trait ślepo wywoływał `parent::afterSave()` bez sprawdzenia czy metoda istnieje

## Rozwiązanie
Zmodyfikowano trait `CompressesImages` aby sprawdzał istnienie metody przed jej wywołaniem:

```php
public function afterCreate(): void
{
    if (method_exists(parent::class, 'afterCreate')) {
        parent::afterCreate();
    }
    $this->compressUploadedImages();
}

public function afterSave(): void
{
    if (method_exists(parent::class, 'afterSave')) {
        parent::afterSave();
    }
    $this->compressUploadedImages();
}
```

## Testowanie
- [x] Brak błędów składniowych w traicie
- [x] Brak błędów w klasach używających traitu
- [x] Trasy aplikacji działają poprawnie
- [x] Cache został wyczyszczony

## Klasy używające traitu
1. `EditEventTemplate` - ma własną metodę `afterSave()`, będzie działać jak wcześniej
2. `EditEventTemplateProgramPoint` - nie ma metody `afterSave()`, teraz nie będzie błędów

## Status
✅ **NAPRAWIONE** - Trait jest teraz kompatybilny z wszystkimi klasami bez względu na to czy mają własne hooki lifecycle.
