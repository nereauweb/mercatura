<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Catalogue resources (products, variants, categories, brands, colours, sizes, attributes): the "catalog.manage" permission. */
final class CatalogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('catalog.manage');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('catalog.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('catalog.manage');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('catalog.manage');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('catalog.manage');
    }

    public function reorder(User $user): bool
    {
        return $user->can('catalog.manage');
    }
}
