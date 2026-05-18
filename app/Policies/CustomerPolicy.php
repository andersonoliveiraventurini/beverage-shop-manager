<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isDeliverer();
    }

    public function view(User $user, Customer $customer): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return ! $user->isDeliverer();
    }

    public function update(User $user, Customer $customer): bool
    {
        return ! $user->isDeliverer();
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->isManager();
    }

    public function restore(User $user, Customer $customer): bool
    {
        return $user->isManager();
    }

    public function forceDelete(User $user, Customer $customer): bool
    {
        return $user->isManager();
    }
}
