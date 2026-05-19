<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Delivery;
use App\Models\User;

class DeliveryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Delivery $delivery): bool
    {
        if ($user->isDeliverer()) {
            return $delivery->deliverer_id === null || $delivery->deliverer_id === $user->id;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Delivery $delivery): bool
    {
        if ($user->isManager()) {
            return true;
        }

        if ($user->isDeliverer()) {
            return $delivery->deliverer_id === null || $delivery->deliverer_id === $user->id;
        }

        return $user->isAttendant();
    }

    public function delete(User $user, Delivery $delivery): bool
    {
        return false;
    }

    public function restore(User $user, Delivery $delivery): bool
    {
        return false;
    }

    public function forceDelete(User $user, Delivery $delivery): bool
    {
        return false;
    }
}
