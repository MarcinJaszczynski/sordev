# TODO: Konfiguracja systemu uprawnień

## Problem rozwiązany tymczasowo

Usunięto sprawdzanie `manage_program_points` permission, ponieważ system uprawnień nie jest jeszcze skonfigurowany w aplikacji.

## Co zostało zrobione

1. **Tymczasowo wyłączono** sprawdzanie uprawnień `can('manage_program_points')`
2. **Zachowano** sprawdzanie uwierzytelnienia (`auth()->check()`)
3. **Dodano komentarze** TODO dla przyszłej implementacji

## Jak wdrożyć pełny system uprawnień

### 1. Zainstaluj Laravel Permission (opcjonalnie)
```bash
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

### 2. Skonfiguruj Model User
```php
// app/Models/User.php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
    // ...
}
```

### 3. Utwórz uprawnienia i role
```php
// database/seeders/PermissionSeeder.php
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

Permission::create(['name' => 'manage_program_points']);
Permission::create(['name' => 'view_program_points']);
Permission::create(['name' => 'edit_program_points']);

$adminRole = Role::create(['name' => 'admin']);
$adminRole->givePermissionTo(['manage_program_points', 'view_program_points', 'edit_program_points']);
```

### 4. Przywróć sprawdzanie uprawnień
W pliku `resources/views/livewire/program-point-children-editor-simple.blade.php`:
```php
@php
    // Przywróć po skonfigurowaniu systemu uprawnień
    if (!auth()->user()->can('manage_program_points')) {
        abort(403, 'Insufficient permissions to manage program points');
    }
@endphp
```

W pliku `app/Livewire/ProgramPointChildrenEditor.php`:
```php
protected function checkPermissions(): void
{
    if (!Auth::check()) {
        $this->logSecurityEvent('unauthorized_access_attempt', 'User not authenticated');
        abort(401, 'Nieautoryzowany dostęp');
    }

    // Przywróć po skonfigurowaniu systemu uprawnień
    if (!Auth::user()->can('manage_program_points')) {
        $this->logSecurityEvent('insufficient_permissions', 'User lacks manage_program_points permission');
        abort(403, 'Niewystarczające uprawnienia do zarządzania punktami programu');
    }
}
```

### 5. Alternatywnie - użyj Laravel Gates
```php
// app/Providers/AuthServiceProvider.php
use Illuminate\Support\Facades\Gate;

public function boot()
{
    Gate::define('manage_program_points', function ($user) {
        // Logika sprawdzania uprawnień
        return $user->is_admin || $user->hasRole('program_manager');
    });
}
```

### 6. Lub stwórz Policy
```bash
php artisan make:policy EventTemplateProgramPointPolicy
```

```php
// app/Policies/EventTemplateProgramPointPolicy.php
public function update(User $user, EventTemplateProgramPoint $programPoint)
{
    return $user->can('manage_program_points') || $user->id === $programPoint->created_by;
}
```

## Obecny stan bezpieczeństwa

✅ **Zabezpieczenia działające:**
- Sprawdzanie uwierzytelnienia
- Rate limiting  
- XSS protection
- Input validation
- SQL injection protection
- CSRF protection
- Security logging

⚠️ **Tymczasowo wyłączone:**
- Granularne sprawdzanie uprawnień
- Role-based access control

## Priorytet implementacji

**Wysoki** - System uprawnień powinien zostać wdrożony przed wejściem do produkcji.

---

**Data:** 30.06.2025  
**Status:** Tymczasowe rozwiązanie - do wdrożenia w kolejnej iteracji
