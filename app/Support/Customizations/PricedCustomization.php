<?php

declare(strict_types=1);

namespace App\Support\Customizations;

use App\Models\ImportData\VariantPrinting;
use App\Models\ImportData\VariantPrintingColor;

/** One chosen option's fixed costs, counted once per line: start ("avviamento") and setup ("impianto"). */
final class PricedCustomization
{
    public function __construct(
        public readonly VariantPrintingColor $option,
        public readonly VariantPrinting $printing,
        public readonly string $label,
        public readonly float $startCost,
        public readonly float $setup,
        public readonly int $setupMultiplier,
        public readonly float $setupPrice,
        public readonly int $minimumQuantity,
        public readonly int $processingDays,
    ) {}
}
