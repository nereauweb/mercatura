<?php

declare(strict_types=1);

namespace App\Support\Customizations;

use App\Models\Customizations\CustomizationOption;

/** One chosen option priced on one article of the line (the option's equivalent on that variant). */
final class PricedArticleCustomization
{
    public function __construct(
        public readonly CustomizationOption $chosen,
        public readonly CustomizationOption $option,
        public readonly string $label,
        public readonly int $quantity,
        public readonly float $unitPrice,
        public readonly float $price,
        public readonly ?float $packagingUnitPrice,
        public readonly float $packagingPrice,
    ) {}
}
