<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Cargo;
use App\Models\User;

class CargoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isManager();
    }

    public function view(User $user, Cargo $cargo): bool
    {
        return $user->isManager();
    }

    public function create(User $user): bool
    {
        return $user->isManager();
    }

    // Cargos are immutable once recorded — they are part of the audit trail.
    public function update(User $user, Cargo $cargo): bool
    {
        return false;
    }

    public function delete(User $user, Cargo $cargo): bool
    {
        return false;
    }

    public function restore(User $user, Cargo $cargo): bool
    {
        return false;
    }

    public function forceDelete(User $user, Cargo $cargo): bool
    {
        return false;
    }
}
