<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A markup band: for an order value (quantity × unit cost) between
 * condition_1 (exclusive) and condition_2 (inclusive), the selling price is
 * the cost plus `value` percent. The same bands serve the import (on the
 * quantities of the price-list tiers) and the storefront (on the quantity
 * actually bought). Two series live side by side (condition_3): 0 standard,
 * 1 "web-shop", used for the products a connector assigns to it
 * (ImportConnector::markupSeries; historically the PF Concept "ws" subtype).
 * Read through App\Support\Connectors\MarkupRules; edited in the admin
 * (Sistema → Regole di prezzo); seeded by CoreSeeder with both series equal.
 *
 * @property int $id
 * @property int $condition_type
 * @property float $condition_1
 * @property float $condition_2
 * @property int $condition_3
 * @property int $delta_type
 * @property float $value
 */
class ProductMarkup extends Model
{
    public const SERIES_STANDARD = 0;

    public const SERIES_WEBSHOP = 1;

    protected $table = 'product_markups';

    protected $fillable = ['condition_type', 'condition_1', 'condition_2', 'condition_3', 'delta_type', 'value'];

    protected $attributes = ['condition_type' => 0, 'delta_type' => 0];

    /** @return array<int, string> */
    public static function seriesOptions(): array
    {
        return [
            self::SERIES_STANDARD => __('admin.pricing.series_standard'),
            self::SERIES_WEBSHOP => __('admin.pricing.series_webshop'),
        ];
    }
}
