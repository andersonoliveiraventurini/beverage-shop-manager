<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WaterShellLedger;

class WaterShellLedgerPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isDeliverer();
    }

    public function view(User $user, WaterShellLedger $waterShellLedger): bool
    {
        return ! $user->isDeliverer();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, WaterShellLedger $waterShellLedger): bool
    {
        return $user->isManager();
    }

    public function delete(User $user, WaterShellLedger $waterShellLedger): bool
    {
        return false;
    }

    public function restore(User $user, WaterShellLedger $waterShellLedger): bool
    {
        return false;
    }

    public function forceDelete(User $user, WaterShellLedger $waterShellLedger): bool
    {
        return false;
    }
}
