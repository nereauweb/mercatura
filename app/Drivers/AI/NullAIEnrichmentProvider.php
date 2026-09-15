<?php

declare(strict_types=1);

namespace App\Drivers\AI;

use App\Contracts\AIEnrichmentProvider;

/** No model configured: callers keep their existing content. */
final class NullAIEnrichmentProvider implements AIEnrichmentProvider
{
    public function key(): string
    {
        return 'null';
    }

    public function available(): bool
    {
        return false;
    }

    public function complete(string $instruction, array $context = []): ?string
    {
        return null;
    }
}
