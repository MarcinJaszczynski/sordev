<?php

declare(strict_types=1);

namespace App\Support\Api;

use App\Models\User;

/**
 * Centralna lista abilities Sanctum dla API v1.
 * Wildcard (*) jest zawsze odrzucany przy tworzeniu tokena.
 */
final class ApiAbilities
{
    public const EVENTS_READ = 'events:read';

    public const EVENTS_WRITE = 'events:write';

    public const TASKS_READ = 'tasks:read';

    public const TASKS_WRITE = 'tasks:write';

    public const NOTIFICATIONS_READ = 'notifications:read';

    public const PILOT_READ = 'pilot:read';

    public const PILOT_WRITE = 'pilot:write';

    public const CLIENT_READ = 'client:read';

    public const CLIENT_WRITE = 'client:write';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::EVENTS_READ,
            self::EVENTS_WRITE,
            self::TASKS_READ,
            self::TASKS_WRITE,
            self::NOTIFICATIONS_READ,
            self::PILOT_READ,
            self::PILOT_WRITE,
            self::CLIENT_READ,
            self::CLIENT_WRITE,
        ];
    }

    /**
     * Domyślne abilities wg ról użytkownika (najszerszy bezpieczny zestaw read).
     *
     * @return list<string>
     */
    public static function defaultsFor(User $user): array
    {
        $abilities = [];

        if ($user->hasRole(['super_admin', 'admin', 'biuro', 'ksiegowosc'])) {
            $abilities = array_merge($abilities, [
                self::EVENTS_READ,
                self::TASKS_READ,
                self::NOTIFICATIONS_READ,
            ]);
        }

        if ($user->hasRole('pilot')) {
            $abilities[] = self::PILOT_READ;
            $abilities[] = self::PILOT_WRITE;
        }

        if ($user->hasRole(['client_participant', 'client_guardian'])) {
            $abilities[] = self::CLIENT_READ;
            $abilities[] = self::CLIENT_WRITE;
        }

        if ($abilities === []) {
            return [self::EVENTS_READ, self::TASKS_READ, self::NOTIFICATIONS_READ];
        }

        return array_values(array_unique($abilities));
    }
}
