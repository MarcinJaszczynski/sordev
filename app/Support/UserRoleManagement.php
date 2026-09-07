<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

final class UserRoleManagement
{
    public const DEFAULT_ROLE = 'pilot';

    public const DEFAULT_TYPE = 'pilot';

    public const DEFAULT_STATUS = 'active';

    public const DEFAULT_CLIENT_ROLE = 'client_participant';

    public const DEFAULT_STAFF_TYPE = 'user';

    /** @return list<string> */
    public static function privilegedRoleNames(): array
    {
        return ['super_admin', 'admin'];
    }

    /** @return list<string> */
    public static function clientRoleNames(): array
    {
        return ['client_participant', 'client_guardian'];
    }

    /** @return list<string> */
    public static function staffRoleNames(): array
    {
        return ['super_admin', 'admin', 'biuro', 'ksiegowosc', 'wlasciciel', 'programista'];
    }

    public static function canManageRolesAndPermissions(?User $actor): bool
    {
        return $actor?->hasRole(self::privilegedRoleNames()) ?? false;
    }

    /** Admin + biuro — piloci i konta portalu uczestników (bez listy staff). */
    public static function canManageOperationalPeople(?User $actor): bool
    {
        return $actor?->hasRole([...self::privilegedRoleNames(), 'biuro']) ?? false;
    }

    public static function defaultPilotRoleId(): ?int
    {
        return Role::query()->where('name', self::DEFAULT_ROLE)->value('id');
    }

    /** @return list<int> */
    public static function defaultPilotRoleIds(): array
    {
        $id = self::defaultPilotRoleId();

        return $id ? [(int) $id] : [];
    }

    public static function defaultClientRoleId(): ?int
    {
        return Role::query()->where('name', self::DEFAULT_CLIENT_ROLE)->value('id');
    }

    /** @return list<int> */
    public static function defaultClientRoleIds(): array
    {
        $id = self::defaultClientRoleId();

        return $id ? [(int) $id] : [];
    }

    public static function isPilotOnlyUser(User $user): bool
    {
        $roles = $user->roles->pluck('name');

        return $roles->count() === 1 && $roles->contains(self::DEFAULT_ROLE);
    }

    public static function isClientPortalUser(User $user): bool
    {
        return $user->roles->pluck('name')->intersect(self::clientRoleNames())->isNotEmpty();
    }

    public static function scopePilotTeamMembers(Builder $query): Builder
    {
        return $query->whereHas('roles', fn (Builder $roleQuery) => $roleQuery->where('name', self::DEFAULT_ROLE))
            ->whereDoesntHave('roles', fn (Builder $roleQuery) => $roleQuery->where('name', '!=', self::DEFAULT_ROLE));
    }

    public static function scopeClientPortalMembers(Builder $query): Builder
    {
        return $query->whereHas(
            'roles',
            fn (Builder $roleQuery) => $roleQuery->whereIn('name', self::clientRoleNames()),
        );
    }

    public static function scopeStaffUsers(Builder $query): Builder
    {
        return $query->whereHas(
            'roles',
            fn (Builder $roleQuery) => $roleQuery->whereIn('name', self::staffRoleNames()),
        );
    }

    /** @param  array<string, mixed>  $data */
    public static function applyPilotDefaults(array $data): array
    {
        $data['status'] = $data['status'] ?? self::DEFAULT_STATUS;
        $data['type'] = self::DEFAULT_TYPE;

        if (! self::canManageRolesAndPermissions(auth()->user())) {
            unset($data['roles'], $data['permissions']);
        }

        return $data;
    }

    /** @param  array<string, mixed>  $data */
    public static function applyClientDefaults(array $data): array
    {
        $data['status'] = $data['status'] ?? self::DEFAULT_STATUS;
        $data['type'] = self::DEFAULT_STAFF_TYPE;
        unset($data['permissions']);

        return $data;
    }

    /** @param  array<string, mixed>  $data */
    public static function applyStaffDefaults(array $data): array
    {
        $data['status'] = $data['status'] ?? self::DEFAULT_STATUS;
        $data['type'] = self::DEFAULT_STAFF_TYPE;

        return $data;
    }

    public static function ensurePilotRole(User $user): void
    {
        if (! $user->hasRole(self::DEFAULT_ROLE)) {
            $user->assignRole(self::DEFAULT_ROLE);
        }

        if ($user->roles()->where('name', '!=', self::DEFAULT_ROLE)->exists()) {
            $user->syncRoles([self::DEFAULT_ROLE]);
        }

        if ($user->permissions()->exists()) {
            $user->syncPermissions([]);
        }

        if ($user->type !== self::DEFAULT_TYPE) {
            $user->forceFill(['type' => self::DEFAULT_TYPE])->save();
        }
    }

    public static function ensureClientPortalRole(User $user): void
    {
        $clientRoles = self::clientRoleNames();
        $current = $user->roles->pluck('name');

        if ($current->intersect($clientRoles)->isEmpty()) {
            $user->syncRoles([self::DEFAULT_CLIENT_ROLE]);
        } else {
            $user->syncRoles($current->intersect($clientRoles)->values()->all());
        }

        if ($user->permissions()->exists()) {
            $user->syncPermissions([]);
        }

        if ($user->type !== self::DEFAULT_STAFF_TYPE) {
            $user->forceFill(['type' => self::DEFAULT_STAFF_TYPE])->save();
        }
    }
}
