<?php

declare(strict_types=1);

namespace App\Support\Customizations;

use App\Models\ProductVariant;

/** One article (variant × quantity) of a priced line. */
final class PricedArticle
{
    /** @param  list<PricedArticleCustomization>  $customizations */
    public function __construct(
        public readonly ProductVariant $variant,
        public readonly int $quantity,
        public readonly float $originalPrice,
        public readonly float $markupPercent,
        public readonly float $unitPrice,
        public readonly float $price,
        public readonly float $additionalCosts,
        public readonly array $customizations,
    ) {}
}
