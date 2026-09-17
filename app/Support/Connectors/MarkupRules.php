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
 * (ImportConnector::markupPercent). Both tables are a handful of rows read
 * millions of times by an import: they are held in memory, in id order (the
 * order the former `first()` queries returned), and dropped when a row is
 * saved or deleted and at the start of every import run.
 */
final class MarkupRules
{
    /** @var list<ProductMarkup>|null */
    private ?array $bands = null;

    /** @var list<NormalizedTiersRule>|null */
    private ?array $tierRules = null;

    public function __construct(private readonly ImportConnectors $connectors) {}

    public function flush(): void
    {
        $this->bands = null;
        $this->tierRules = null;
    }

    public function rule(float $condition): ?ProductMarkup
    {
        $this->bands ??= array_values(ProductMarkup::query()->orderBy('id')->get()->all());
        foreach ($this->bands as $band) {
            if ((float) $band->from_condition < $condition && (float) $band->to_condition >= $condition) {
                return $band;
            }
        }

        return null;
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
        $this->tierRules ??= array_values(NormalizedTiersRule::query()->orderBy('id')->get()->all());
        $band = null;
        foreach ($this->tierRules as $rule) {
            if ((float) $rule->from_price <= $cost && (float) $rule->to_price > $cost) {
                $band = $rule;
                break;
            }
        }
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
