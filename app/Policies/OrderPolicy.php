<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/** Admin panel access to orders: the "orders.manage" permission (CoreSeeder). */
final class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('orders.manage');
    }

    public function view(User $user, Order $order): bool
    {
        return $user->can('orders.manage');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Order $order): bool
    {
        return $user->can('orders.manage');
    }

    public function delete(User $user, Order $order): bool
    {
        return false;
    }
}
