<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Product recommendations and behavioural events. Selected by
 * config('mercatura.providers.personalization'). v2a ships the contract
 * and the null driver only.
 */
interface PersonalizationProvider
{
    /** Driver name, e.g. "null". */
    public function key(): string;

    /**
     * Product ids to recommend, most relevant first. Empty means "use the
     * catalogue defaults" (related products, bestsellers).
     *
     * @param  array<string, mixed>  $context  e.g. ['product_id' => 12, 'category_id' => 3]
     * @return list<int>
     */
    public function recommendedProductIds(?Authenticatable $user, array $context = [], int $limit = 8): array;

    /**
     * Record a behavioural event (view, add_to_cart, purchase…).
     *
     * @param  array<string, mixed>  $payload
     */
    public function track(string $event, array $payload = []): void;
}
