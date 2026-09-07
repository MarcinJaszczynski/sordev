<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $user->hasRole('admin');
    }
}
