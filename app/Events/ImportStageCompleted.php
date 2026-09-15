<?php

declare(strict_types=1);

namespace App\Events;

use App\Contracts\ImportConnector;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired by the import command and jobs when a stage has finished
 * (ImportConnector::STAGE_PRODUCTS after the normalized data is processed,
 * STAGE_PRINTINGS after the printing variants are cleaned up). Installation
 * packages listen to it for their own post-import work (feeds, exports);
 * the core runs nothing after an import.
 */
final class ImportStageCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly int $importId,
        public readonly string $stage,
        public readonly string $processSource = 'all',
    ) {}

    public function isProducts(): bool
    {
        return $this->stage === ImportConnector::STAGE_PRODUCTS;
    }

    public function isPrintings(): bool
    {
        return $this->stage === ImportConnector::STAGE_PRINTINGS;
    }
}
