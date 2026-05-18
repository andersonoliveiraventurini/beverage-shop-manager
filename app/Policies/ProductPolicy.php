<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isDeliverer();
    }

    public function view(User $user, Product $product): bool
    {
        return ! $user->isDeliverer();
    }

    public function create(User $user): bool
    {
        return $user->isManager();
    }

    public function update(User $user, Product $product): bool
    {
        return $user->isManager();
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->isManager();
    }

    public function restore(User $user, Product $product): bool
    {
        return $user->isManager();
    }

    public function forceDelete(User $user, Product $product): bool
    {
        return $user->isManager();
    }
}
