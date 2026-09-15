<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

/** Admin panel access to messages: the "orders.manage" permission (CoreSeeder). */
final class MessagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('orders.manage');
    }

    public function view(User $user, Message $message): bool
    {
        return $user->can('orders.manage');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Message $message): bool
    {
        return false;
    }

    public function delete(User $user, Message $message): bool
    {
        return false;
    }
}
