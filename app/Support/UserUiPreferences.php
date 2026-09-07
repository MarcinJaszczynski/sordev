<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Trwałe preferencje UI per użytkownik (JSON na users.ui_preferences).
 */
class UserUiPreferences
{
    /**
     * @return array<string, mixed>
     */
    public static function get(string $key, array $default = []): array
    {
        $user = Auth::user();

        if (! $user instanceof User || ! self::columnExists()) {
            return $default;
        }

        $value = data_get($user->ui_preferences ?? [], $key, $default);

        return is_array($value) ? $value : $default;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    public static function put(string $key, array $value): void
    {
        $user = Auth::user();

        if (! $user instanceof User || ! self::columnExists()) {
            return;
        }

        $prefs = is_array($user->ui_preferences) ? $user->ui_preferences : [];
        $existing = data_get($prefs, $key, []);
        $merged = array_merge(is_array($existing) ? $existing : [], $value);

        if ($existing === $merged) {
            return;
        }

        data_set($prefs, $key, $merged);
        $user->forceFill(['ui_preferences' => $prefs])->save();
    }

    private static function columnExists(): bool
    {
        return Schema::hasColumn('users', 'ui_preferences');
    }
}
