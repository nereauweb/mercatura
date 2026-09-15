<?php

declare(strict_types=1);

namespace App\Support\Customizations;

use App\Models\ImportData\VariantPrintingColor;

/** One chosen option priced on one article of the line (the option's equivalent on that variant). */
final class PricedArticleCustomization
{
    public function __construct(
        public readonly VariantPrintingColor $chosen,
        public readonly VariantPrintingColor $option,
        public readonly string $label,
        public readonly int $quantity,
        public readonly float $unitPrice,
        public readonly float $price,
        public readonly ?float $packagingUnitPrice,
        public readonly float $packagingPrice,
    ) {}
}
