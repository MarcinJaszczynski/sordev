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

    /** @return list<string> */
    public static function privilegedRoleNames(): array
    {
        return ['super_admin', 'admin'];
    }

    public static function canManageRolesAndPermissions(?User $actor): bool
    {
        return $actor?->hasRole(self::privilegedRoleNames()) ?? false;
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

    public static function isPilotOnlyUser(User $user): bool
    {
        $roles = $user->roles->pluck('name');

        return $roles->count() === 1 && $roles->contains(self::DEFAULT_ROLE);
    }

    public static function scopePilotTeamMembers(Builder $query): Builder
    {
        return $query->whereHas('roles', fn (Builder $roleQuery) => $roleQuery->where('name', self::DEFAULT_ROLE))
            ->whereDoesntHave('roles', fn (Builder $roleQuery) => $roleQuery->where('name', '!=', self::DEFAULT_ROLE));
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
}
