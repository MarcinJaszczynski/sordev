<?php

namespace App\Policies;

use App\Models\Markup;
use App\Models\User;

class MarkupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, Markup $markup): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Markup $markup): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Markup $markup): bool
    {
        return $user->hasRole('admin');
    }

    public function restore(User $user, Markup $markup): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, Markup $markup): bool
    {
        return $user->hasRole('admin');
    }
}
