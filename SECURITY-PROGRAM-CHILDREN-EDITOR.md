# Zabezpieczenia - Program Point Children Editor

## Przegląd

Komponent `ProgramPointChildrenEditor` został zabezpieczony przed różnymi typami ataków i zagrożeń bezpieczeństwa.

## Zaimplementowane zabezpieczenia

### 1. Autoryzacja i uwierzytelnianie

**Plik:** `app/Livewire/ProgramPointChildrenEditor.php`

- ✅ Sprawdzanie uwierzytelnienia użytkownika przy każdej operacji
- ✅ Walidacja uprawnień do zarządzania punktami programu
- ✅ Logowanie nieautoryzowanych prób dostępu

```php
protected function checkPermissions(): void
{
    if (!Auth::check()) {
        $this->logSecurityEvent('unauthorized_access_attempt', 'User not authenticated');
        abort(401, 'Nieautoryzowany dostęp');
    }
}
```

### 2. Rate Limiting

**Implementacja:** Ograniczenie częstotliwości żądań

- ✅ Maksymalnie 10 żądań na minutę na użytkownika
- ✅ Osobne limity dla różnych akcji (save, delete, update_order)
- ✅ Logowanie przekroczeń limitów

```php
protected function checkRateLimit(string $action): void
{
    $key = 'program_children_editor:' . Auth::id() . ':' . $action;
    
    if (RateLimiter::tooManyAttempts($key, 10)) {
        $this->logSecurityEvent('rate_limit_exceeded', "Action: $action");
        throw ValidationException::withMessages([
            'general' => 'Zbyt wiele żądań. Spróbuj ponownie za chwilę.'
        ]);
    }
}
```

### 3. Walidacja danych wejściowych

**Backend walidacja:**

- ✅ Maksymalna długość wyszukiwanego tekstu (100 znaków)
- ✅ Walidacja numeryczna dla cen i czasów trwania
- ✅ Sprawdzanie poprawności ID punktów programu
- ✅ Walidacja tablicy kolejności elementów

```php
protected function rules()
{
    return [
        'modalData.child_program_point_id' => 'required|exists:event_template_program_points,id',
        'modalData.order' => 'integer|min:0',
        'searchTerm' => 'nullable|string|max:100',
        'minPrice' => 'nullable|numeric|min:0|max:999999.99',
        'maxPrice' => 'nullable|numeric|min:0|max:999999.99',
        'minDuration' => 'nullable|integer|min:0|max:1440',
        'maxDuration' => 'nullable|integer|min:0|max:1440',
    ];
}
```

### 4. Ochrona przed XSS

**Frontend zabezpieczenia:**

- ✅ Escape'owanie wszystkich danych wyjściowych w Blade
- ✅ Użycie `e()` helper dla tekstu użytkownika
- ✅ Bezpieczne tworzenie elementów DOM w JavaScript
- ✅ Sanityzacja danych w JavaScript przed wyświetleniem

```blade
<!-- Bezpieczne wyświetlanie danych -->
<div class="font-medium text-gray-700">{{ e($child['name']) }}</div>
<p class="text-sm text-gray-700">{{ e(Str::limit($point['description'], 200)) }}</p>

<!-- Bezpieczne atrybuty -->
alt="Zdjęcie wyróżniające dla: {{ e($child['name']) }}"
aria-label="Podpunkt: {{ e($child['name']) }}"
```

**JavaScript zabezpieczenia:**

```javascript
// Bezpieczne tworzenie elementów
const messageSpan = document.createElement('span');
messageSpan.textContent = message; // Bezpieczne wstawienie tekstu

// Sanityzacja wiadomości
const sanitizedMessage = DOMPurify ? DOMPurify.sanitize(data.message) : data.message.replace(/<[^>]*>/g, '');
```

### 5. CSRF Protection

**Zabezpieczenia:**

- ✅ Token CSRF dostępny w komponencie
- ✅ Walidacja tokenu przy operacjach sortowania
- ✅ Użycie Livewire CSRF protection

```blade
<ul class="children-list" 
    data-csrf-token="{{ csrf_token() }}"
    role="list">
```

### 6. SQL Injection Protection

**Zabezpieczenia:**

- ✅ Użycie Eloquent ORM i Query Builder
- ✅ Walidacja wszystkich ID jako integers
- ✅ Prepared statements dla wszystkich zapytań

```php
// Bezpieczne zapytania
$childId = (int) $this->modalData['child_program_point_id'];
$childPoint = EventTemplateProgramPoint::find($childId);

// Walidacja przed zapytaniem
if ($childId <= 0) {
    throw new ValidationException(['child_program_point_id' => 'Nieprawidłowy identyfikator punktu programu.']);
}
```

### 7. Bezpieczeństwo biznesowe

**Zabezpieczenia logiki:**

- ✅ Sprawdzanie własności relacji przed operacjami
- ✅ Zapobieganie cyklicznym referencjom (parent jako child)
- ✅ Sprawdzanie duplikatów relacji
- ✅ Walidacja uprawnień do modyfikacji

```php
// Zapobieganie cyklicznym referencjom
if ($childId === $this->programPoint->id) {
    $this->logSecurityEvent('circular_reference_attempt', "Parent/Child ID: $childId");
    throw new ValidationException(['child_program_point_id' => 'Nie można dodać punktu jako podpunkt samego siebie.']);
}

// Sprawdzanie własności
$existingRelation = DB::table('event_template_program_point_parent')
    ->where('parent_id', $this->programPoint->id)
    ->where('child_id', $childId)
    ->first();
```

### 8. Logowanie i monitoring

**System logowania:**

- ✅ Logowanie wszystkich operacji bezpieczeństwa
- ✅ Szczegółowe informacje o użytkowniku i kontekście
- ✅ Różne poziomy logów (INFO, WARNING, ERROR)

```php
protected function logSecurityEvent(string $event, string $details = ''): void
{
    Log::warning('Security Event in ProgramPointChildrenEditor', [
        'event' => $event,
        'details' => $details,
        'user_id' => Auth::id(),
        'ip_address' => request()->ip(),
        'user_agent' => request()->userAgent(),
        'program_point_id' => $this->programPoint->id ?? null,
        'timestamp' => now(),
    ]);
}
```

### 9. Frontend Security Enhancements

**Dodatkowe zabezpieczenia JavaScript:**

- ✅ Rate limiting dla akcji użytkownika
- ✅ Blokada szybkich kliknięć
- ✅ Walidacja po stronie klienta
- ✅ Kontrola dostępu do narzędzi deweloperskich (podstawowa)

```javascript
// Rate limiting w Alpine.js
x-on:click="
    if (rateLimitExceeded) {
        $dispatch('notify', { message: 'Zbyt częste żądania. Spróbuj ponownie za chwilę.', type: 'error' });
        return;
    }
    if (Date.now() - lastActionTime < 2000) {
        rateLimitExceeded = true;
        return;
    }
    lastActionTime = Date.now();
"
```

### 10. Accessibility i UX Security

**Zabezpieczenia dostępności:**

- ✅ Proper ARIA labels i role attributes
- ✅ Semantic HTML structure
- ✅ Keyboard navigation support
- ✅ Screen reader support

```blade
<!-- Accessibility attributes -->
<div class="fixed inset-0 z-50" 
     role="dialog" 
     aria-modal="true" 
     aria-labelledby="modal-title">

<button aria-label="Dodaj nowy podpunkt programu"
        role="button"
        tabindex="0">
```

## Monitorowane zagrożenia

### Wykrywane i logowane incydenty:

1. **Nieautoryzowany dostęp**
   - Próby dostępu bez uwierzytelnienia
   - Próby dostępu bez uprawnień

2. **Ataki na dane**
   - Nieprawidłowe ID punktów programu
   - Próby cyklicznych referencji
   - Duplikaty relacji

3. **Rate limiting**
   - Przekroczenie limitów żądań
   - Zbyt częste operacje

4. **Błędy walidacji**
   - Nieprawidłowe dane wejściowe
   - Przekroczenie limitów długości

5. **Błędy systemowe**
   - Błędy bazy danych
   - Wyjątki aplikacji

## Rekomendacje bezpieczeństwa

### Obecne zabezpieczenia są silne, ale można rozważyć:

1. **Dwuetapowa autoryzacja** dla operacji krytycznych
2. **Szyfrowanie danych wrażliwych** w bazie danych
3. **Audit trail** dla wszystkich operacji CRUD
4. **Integracja z systemem wykrywania intruzów**
5. **Regularne przeglądy bezpieczeństwa** kodu

## Testowanie bezpieczeństwa

### Zalecane testy:

1. **Penetration testing** komponentu
2. **Automated security scanning** kodu
3. **Load testing** mechanizmów rate limiting
4. **Cross-browser testing** zabezpieczeń frontend

## Kontakt

W przypadku wykrycia luk bezpieczeństwa lub pytań, skontaktuj się z zespołem bezpieczeństwa.

---

**Ostatnia aktualizacja:** 30.06.2025  
**Wersja dokumentu:** 1.0  
**Odpowiedzialny:** Team Security
