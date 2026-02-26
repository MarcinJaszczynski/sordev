# 🖼️ Kompresja Obrazów - Instrukcja

## Przegląd

System automatycznej kompresji obrazów został dodany do optymalizatora zasobów. Kompresuje uploadowane zdjęcia w Filament automatycznie, oszczędzając miejsce na serwerze i przyspiesza ładowanie strony.

## Funkcje

✅ **Automatyczna kompresja** podczas uploadu w Filament  
✅ **Zmiana rozmiaru** dużych obrazów (max 1920x1080px)  
✅ **Konwersja do WebP** dla lepszej kompresji  
✅ **Generowanie miniatur** (300px)  
✅ **Optymalizacja jakości** (JPEG: 85%, WebP: 85%)  
✅ **Kompresja istniejących obrazów**  

## Instalacja

### 1. Automatyczna kompresja w Filament

Dodaj trait `CompressesImages` do stron Filament z uploadem obrazów:

```php
<?php

namespace App\Filament\Resources\EventTemplateResource\Pages;

use App\Traits\CompressesImages;
use Filament\Resources\Pages\EditRecord;

class EditEventTemplate extends EditRecord
{
    use CompressesImages;
    
    // Opcjonalnie: dostosuj pola do kompresji
    protected function getImageFields(): array
    {
        return [
            'featured_image',
            'gallery',
            'gallery_images',
            // dodaj swoje pola
        ];
    }
    
    // Opcjonalnie: zmień dysk przechowywania
    protected function getImageDisk(): string
    {
        return 'public'; // lub 's3', 'local', itd.
    }
}
```

### 2. Kompresja istniejących obrazów

```bash
# Sprawdź konfigurację
php artisan images:compress

# Kompresuj istniejące obrazy
php artisan images:compress --existing

# Kompresuj w konkretnym katalogu
php artisan images:compress --existing --directory=gallery

# Użyj innego dysku
php artisan images:compress --existing --disk=s3
```

## Jak to działa

### Automatyczna kompresja

1. **Upload przez Filament**: Użytkownik uploaduje obraz
2. **Zapisanie**: Filament zapisuje plik normalnie
3. **Hook**: Trait `CompressesImages` przechwytuje zapis
4. **Kompresja**: Obraz jest kompresowany i optymalizowany
5. **Zastąpienie**: Oryginalny plik jest zastępowany skompresowanym

### Proces kompresji

```
Oryginalny obraz (np. 5MB, 4000x3000px)
    ↓
Zmiana rozmiaru → 1920x1080px
    ↓
Kompresja JPEG → jakość 85%
    ↓
Konwersja WebP → jakość 85%
    ↓
Miniatura → 300x300px
    ↓
Wynik: 3 pliki (~500KB, ~200KB WebP, ~50KB miniatura)
```

## Konfiguracja

### Parametry kompresji

```php
// app/Services/ImageCompressionService.php

public const JPEG_QUALITY = 85;      // Jakość JPEG (1-100)
public const WEBP_QUALITY = 85;      // Jakość WebP (1-100)
public const MAX_WIDTH = 1920;       // Maksymalna szerokość
public const MAX_HEIGHT = 1080;      // Maksymalna wysokość
public const THUMBNAIL_SIZE = 300;   // Rozmiar miniatur
```

### Obsługiwane formaty

- **JPEG/JPG** → Kompresja z jakością 85%
- **PNG** → Optymalizacja bez straty jakości
- **GIF** → Zachowanie animacji
- **WebP** → Już zoptymalizowane

## Filament FileUpload - Ustawienia

### Podstawowe pole z kompresją

```php
Forms\Components\FileUpload::make('featured_image')
    ->label('Zdjęcie główne')
    ->image()
    ->directory('images')
    ->disk('public')
    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
    ->maxSize(10240) // 10MB przed kompresją
```

### Galeria z kompresją

```php
Forms\Components\FileUpload::make('gallery_images')
    ->label('Galeria')
    ->image()
    ->multiple()
    ->directory('gallery')
    ->disk('public')
    ->reorderable()
    ->maxFiles(20)
    ->imageEditor()
    ->imageEditorAspectRatios(['16:9', '4:3', '1:1'])
```

## Przykłady użycia

### 1. EventTemplate z obrazami

```php
// app/Filament/Resources/EventTemplateResource/Pages/EditEventTemplate.php

class EditEventTemplate extends EditRecord
{
    use CompressesImages;
    
    protected function getImageFields(): array
    {
        return ['featured_image', 'gallery'];
    }
}
```

### 2. User z awatarem

```php
// app/Filament/Resources/UserResource/Pages/EditUser.php

class EditUser extends EditRecord  
{
    use CompressesImages;
    
    protected function getImageFields(): array
    {
        return ['avatar'];
    }
}
```

### 3. Task z załącznikami

```php
// app/Filament/Resources/TaskResource/Pages/EditTask.php

class EditTask extends EditRecord
{
    use CompressesImages;
    
    protected function getImageFields(): array
    {
        return ['attachments']; // Tylko obrazy będą kompresowane
    }
}
```

## Monitorowanie

### Statystyki kompresji

Po kompresji zobaczysz:

```bash
php artisan images:compress --existing

📊 Compression Statistics:
• Total space saved: 15.2 MB
• Overall compression: 68.3%
• Successfully processed: 45 files
```

### Logi błędów

Błędy kompresji są logowane:

```php
// storage/logs/laravel.log

[2025-06-30 13:00:00] local.ERROR: Image compression failed
{
    "field": "featured_image",
    "file": "images/photo_123.jpg", 
    "error": "Unsupported image format"
}
```

## Wydajność

### Przed kompresją

| Typ pliku | Średni rozmiar | Czas ładowania |
|-----------|----------------|----------------|
| JPEG z aparatu | 5-8 MB | 2-4 sekundy |
| PNG screenshot | 2-3 MB | 1-2 sekundy |
| Gallery (10 zdjęć) | 50+ MB | 20+ sekund |

### Po kompresji

| Typ pliku | Średni rozmiar | Czas ładowania | Oszczędność |
|-----------|----------------|----------------|-------------|
| JPEG (85%) | 800KB-1.2MB | 0.3-0.6 sek | **75-80%** |
| WebP | 300-500KB | 0.1-0.3 sek | **85-90%** |
| Gallery (10 zdjęć) | 8-12 MB | 3-5 sekund | **75-80%** |

## Best Practices

### 1. Konfiguracja Filament

```php
// Zawsze ustaw limity
FileUpload::make('image')
    ->maxSize(10240)        // 10MB limit
    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
    ->image()               // Tylko obrazy
    ->imageEditor()         // Pozwól edycję
```

### 2. Struktura katalogów

```
storage/app/public/
├── images/           # Główne obrazy
│   ├── thumbs/      # Miniatury
│   └── webp/        # Wersje WebP
├── gallery/         # Galerie
├── avatars/         # Awatary użytkowników
└── temp/           # Pliki tymczasowe
```

### 3. Frontend - wykorzystanie WebP

```html
<!-- W blade templates -->
<picture>
    <source srcset="{{ Storage::url($image_webp) }}" type="image/webp">
    <img src="{{ Storage::url($image_original) }}" alt="Opis">
</picture>
```

## Rozwiązywanie problemów

### 1. Brak kompresji

**Problem**: Obrazy nie są kompresowane
**Rozwiązanie**:
- Sprawdź czy trait `CompressesImages` jest dodany
- Sprawdź czy pole jest w `getImageFields()`
- Sprawdź logi błędów

### 2. Błąd "Memory limit"

**Problem**: Duże obrazy powodują błąd pamięci
**Rozwiązanie**:
```php
// config/image.php (jeśli zostanie utworzony)
'memory_limit' => '256M',

// lub w .env
MEMORY_LIMIT=256M
```

### 3. Słaba jakość

**Problem**: Obrazy po kompresji są zbyt słabe
**Rozwiązanie**:
```php
// Zwiększ jakość w ImageCompressionService
public const JPEG_QUALITY = 90; // było 85
public const WEBP_QUALITY = 90; // było 85
```

## Kompatybilność

✅ **Filament 3.x**: Pełna obsługa FileUpload  
✅ **Laravel 12**: Natywne wsparcie Storage  
✅ **Intervention Image 3.x**: Nowoczesna biblioteka  
✅ **GD/ImageMagick**: Automatyczne wykrywanie  
✅ **Disk drivers**: Local, S3, FTP, itp.  

## Bezpieczeństwo

### Walidacja plików

```php
FileUpload::make('image')
    ->acceptedFileTypes(['image/jpeg', 'image/png'])
    ->maxSize(10240)
    ->rules(['image', 'mimes:jpeg,png,jpg', 'max:10240'])
```

### Zabezpieczenie przed szkodliwymi plikami

- Automatyczna walidacja formatu
- Sprawdzanie rzeczywistego typu MIME
- Regeneracja pliku przez bibliotekę Image
- Usuwanie metadanych EXIF

Kompresja obrazów jest teraz w pełni skonfigurowana i gotowa do użycia! 🚀
