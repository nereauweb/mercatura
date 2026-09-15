<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** CMS pages, home slides, blog, redirects: the "content.manage" permission. */
final class ContentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('content.manage');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('content.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('content.manage');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('content.manage');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('content.manage');
    }

    public function reorder(User $user): bool
    {
        return $user->can('content.manage');
    }
}
