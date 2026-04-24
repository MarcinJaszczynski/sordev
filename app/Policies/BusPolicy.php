<?php

namespace App\Policies;

use App\Models\Bus;
use App\Models\User;

class BusPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, Bus $bus): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Bus $bus): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Bus $bus): bool
    {
        return $user->hasRole('admin');
    }
}
