<?php

namespace App\Support\Tasks;

use App\Models\User;
use Illuminate\Support\Collection;

final class OfficeTaskRecipients
{
    /**
     * @return Collection<int, User>
     */
    public static function users(): Collection
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['admin', 'super_admin', 'biuro']))
            ->get();
    }
}
