<?php

declare(strict_types=1);

namespace App\Support\Connectors;

use App\Models\ImportData\NormalizedTiersRule;
use App\Models\ProductMarkup;
use App\Support\ImportConnectors;

/**
 * Selling price from unit cost: markup bands in product_markups
 * (condition = quantity × cost) and quantity breaks in normalized_tiers_rules.
 * Connectors may adjust the percent for their own sources
 * (ImportConnector::markupPercent).
 */
final class MarkupRules
{
    public function __construct(private readonly ImportConnectors $connectors) {}

    public function rule(float $condition): ?ProductMarkup
    {
        return ProductMarkup::query()
            ->where('condition_1', '<', $condition)
            ->where('condition_2', '>=', $condition)
            ->first();
    }

    public function percent(float $condition, ?string $source, ?string $sku): float
    {
        $rule = $this->rule($condition);
        $percent = $rule ? (float) $rule->value : 0.0;
        $connector = $this->connectors->forSource($source);

        return $connector ? $connector->markupPercent($percent, $rule, $condition, $sku) : $percent;
    }

    /** Selling price for a quantity (legacy ImportCommand::normalized_price). */
    public function price(int|float $quantity, float $cost, ?string $source = null, ?string $sku = null): float
    {
        $percent = $this->percent((float) $quantity * $cost, $source, $sku);

        return $cost * (1 + $percent / 100);
    }

    /**
     * Quantity tiers with selling prices for a unit cost (legacy generate_tiers_normalized_prices).
     *
     * @return list<array{from_quantity: int, normalized_price: float}>
     */
    public function tiers(float $cost, ?string $source = null, ?string $sku = null): array
    {
        $band = NormalizedTiersRule::query()->where('from_price', '<=', $cost)->where('to_price', '>', $cost)->first();
        if (! $band) {
            $single = $cost;
            if ($this->connectors->forSource($source)?->appliesMarkupToSingleTier($sku)) {
                $single = $this->price(1, $cost, $source, $sku);
            }

            return [['from_quantity' => 1, 'normalized_price' => $single]];
        }

        $tiers = [];
        foreach (['from_quantity_1', 'from_quantity_2', 'from_quantity_3', 'from_quantity_4'] as $column) {
            $quantity = (int) $band->{$column};
            $tiers[] = ['from_quantity' => $quantity, 'normalized_price' => $this->price($quantity, $cost, $source, $sku)];
        }

        return $tiers;
    }
}
