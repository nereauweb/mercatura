<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

/** Admin panel access to customers: the "orders.manage" permission (CoreSeeder). */
final class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('orders.manage');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->can('orders.manage');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->can('orders.manage');
    }

    public function delete(User $user, Customer $customer): bool
    {
        return false;
    }
}
