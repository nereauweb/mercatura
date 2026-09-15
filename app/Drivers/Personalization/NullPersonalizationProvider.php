<?php

declare(strict_types=1);

namespace App\Drivers\Personalization;

use App\Contracts\PersonalizationProvider;
use Illuminate\Contracts\Auth\Authenticatable;

/** No personalization: no recommendations, events are dropped. */
final class NullPersonalizationProvider implements PersonalizationProvider
{
    public function key(): string
    {
        return 'null';
    }

    public function recommendedProductIds(?Authenticatable $user, array $context = [], int $limit = 8): array
    {
        return [];
    }

    public function track(string $event, array $payload = []): void
    {
        // Intentionally empty.
    }
}
