<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A markup band: for an order value (quantity × unit cost) between
 * condition_1 (exclusive) and condition_2 (inclusive), the selling price is
 * the cost plus `value` percent. Two series live side by side (condition_3):
 * 0 for the prices computed at import, 1 for the storefront (cart and
 * configurator). Read through App\Support\Connectors\MarkupRules; edited in
 * the admin (Sistema → Regole di prezzo); seeded by CoreSeeder.
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
    public const SERIES_IMPORT = 0;

    public const SERIES_STOREFRONT = 1;

    protected $table = 'product_markups';

    protected $fillable = ['condition_type', 'condition_1', 'condition_2', 'condition_3', 'delta_type', 'value'];

    protected $attributes = ['condition_type' => 0, 'delta_type' => 0];

    /** @return array<int, string> */
    public static function seriesOptions(): array
    {
        return [
            self::SERIES_IMPORT => __('admin.pricing.series_import'),
            self::SERIES_STOREFRONT => __('admin.pricing.series_storefront'),
        ];
    }
}
