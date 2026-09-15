<?php

namespace App\Models\ImportData;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class NormalizedProductVariant extends Model
{
    protected $table = 'normalized_products_variants';

    protected $fillable = [
        'product_id',
        'source_id',
        'name',
        'short_description',
        'full_description',
        'stock',
        'next_stock_date',
        'next_stock_quantity',
        'weightG',
        'packagingGrossWeightKg',
        'packagingNetWeightKg',
        'packageHeightCm',
        'packageWidthCm',
        'packageDepthCm',
        'material',
        'countryOfOrigin',
        'brand',
        'qtyPerCarton',
        'size',
        'keywords',
        'theme',
        'customization_default_technique',
        'customization_default_location',
        'customization_default_dimension',
        'customization_default_max_colors',
        'gender',
        'markSegment',
        'sale',
        'related',
        'ink_color',
        'dimensions',
        'last_seen_active',
    ];

    public function images(): HasMany
    {
        return $this->HasMany(NormalizedProductVariantImage::class, 'variant_id');
    }

    public function prices(): HasMany
    {
        return $this->HasMany(NormalizedProductVariantPrice::class, 'variant_id');
    }

    public function ordered_prices($order = 'asc')
    {
        return $this->prices()->orderBy('from_quantity', $order)->get();
    }

    public function original_price_per_quantity($quantity)
    {
        foreach ($this->ordered_prices('desc') as $price) {
            if ($price->from_quantity <= $quantity) {
                return floatval($price->original_price);
            }
        }

        return 0;
    }

    public function color(): HasOne
    {
        return $this->HasOne(NormalizedProductVariantColor::class, 'variant_id');
    }

    public function colors(): HasMany
    {
        return $this->HasMany(NormalizedProductVariantColor::class, 'variant_id');
    }

    public function app_variant(): HasOne
    {
        return $this->HasOne(\App\Models\ProductVariant::class, 'source_sku', 'source_id');
    }

    public function customizations(): HasMany
    {
        return $this->HasMany(\App\Models\Customizations\Customization::class, 'normalized_variant_id');
    }
}
