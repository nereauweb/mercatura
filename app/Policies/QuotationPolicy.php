<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Quotation;
use App\Models\User;

/** Admin panel access to quotations: the "orders.manage" permission (CoreSeeder). */
final class QuotationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('orders.manage');
    }

    public function view(User $user, Quotation $quotation): bool
    {
        return $user->can('orders.manage');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Quotation $quotation): bool
    {
        return false;
    }

    public function delete(User $user, Quotation $quotation): bool
    {
        return $user->can('orders.manage');
    }
}
