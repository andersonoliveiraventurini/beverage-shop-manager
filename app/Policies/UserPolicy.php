<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isManager();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isManager();
    }

    public function create(User $user): bool
    {
        return $user->isManager();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isManager();
    }

    /**
     * Managers cannot delete their own account — the system must never be
     * left without someone able to manage users.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->isManager() && ! $user->is($model);
    }

    public function restore(User $user, User $model): bool
    {
        return $user->isManager();
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $user->isManager() && ! $user->is($model);
    }
}
