<?php

declare(strict_types=1);

namespace App\Support\Customizations;

use App\Models\Product;

/**
 * A configured line: articles of one product with the customizations chosen
 * once for all of them. `price` is the line total excluding VAT and the
 * VAT-free additional costs (SIAE-like), which are in `additionalCosts`.
 */
final class PricedLine
{
    /**
     * @param  list<PricedArticle>  $articles
     * @param  list<PricedCustomization>  $customizations
     */
    public function __construct(
        public readonly Product $product,
        public readonly int $quantity,
        public readonly bool $packaging,
        public readonly array $articles,
        public readonly array $customizations,
        public readonly int $minimumQuantity,
        public readonly float $surcharge,
        public readonly int $processingDays,
        public readonly float $additionalCosts,
        public readonly float $price,
        public readonly bool $sample = false,
    ) {}

    public function underMinimum(): bool
    {
        return $this->surcharge > 0;
    }

    /** Line price per piece, excluding VAT, additional costs excluded (the cart's reading). */
    public function unitPrice(): float
    {
        return round($this->price / $this->quantity, 2);
    }

    /** Line price per piece with the additional costs (the configurator's reading). */
    public function unitPriceWithAdditionalCosts(): float
    {
        return ($this->price + $this->additionalCosts) / $this->quantity;
    }

    public function vat(): float
    {
        return Pricing::vat($this->price);
    }

    public function totalTaxedPrice(): float
    {
        return $this->price + $this->vat() + $this->additionalCosts;
    }
}
