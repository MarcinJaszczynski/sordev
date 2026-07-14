<?php

namespace App\Filament\Concerns;

use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\FilamentShield\Support\Utils;
use Illuminate\Database\Eloquent\Model;

/**
 * Autoryzacja zasobów przez Filament Shield (z fallbackiem dla admin/super_admin).
 */
trait AuthorizesWithShield
{
    public static function canViewAny(): bool
    {
        return static::authorizeShield('view_any');
    }

    public static function canView(Model $record): bool
    {
        return static::authorizeShield('view');
    }

    public static function canCreate(): bool
    {
        return static::authorizeShield('create');
    }

    public static function canEdit(Model $record): bool
    {
        return static::authorizeShield('update');
    }

    public static function canDelete(Model $record): bool
    {
        return static::authorizeShield('delete');
    }

    protected static function authorizeShield(string $prefix): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->hasRole([Utils::getSuperAdminName(), 'admin'])) {
            return true;
        }

        return $user->can(static::shieldPermission($prefix));
    }

    protected static function shieldPermission(string $prefix): string
    {
        $identifier = FilamentShield::getPermissionIdentifier(static::class);

        return "{$prefix}_{$identifier}";
    }
}
