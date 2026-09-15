<?php

declare(strict_types=1);

namespace App\Support\Customizations;

use App\Models\Customizations\Customization;
use App\Models\Customizations\CustomizationOption;

/** One chosen option's fixed costs, counted once per line: start ("avviamento") and setup ("impianto"). */
final class PricedCustomization
{
    public function __construct(
        public readonly CustomizationOption $option,
        public readonly Customization $printing,
        public readonly string $label,
        public readonly float $startCost,
        public readonly float $setup,
        public readonly int $setupMultiplier,
        public readonly float $setupPrice,
        public readonly int $minimumQuantity,
        public readonly int $processingDays,
    ) {}
}
