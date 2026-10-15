<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\MessageType;
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

    /** A report not yet forwarded, by whoever holds "messages.forward" (docs/06_USER_REPORTS.md §6). */
    public function forward(User $user, Message $message): bool
    {
        return $user->can('messages.forward') && $message->type === MessageType::Report && $message->forwarded_at === null;
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
