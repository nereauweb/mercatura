<?php

namespace App\Models\ImportData;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string|null $default_customization_technique
 * @property string|null $default_customization_position
 * @property string|null $default_customization_dimension
 * @property string|null $default_customization_max_colors
 */
class NormalizedProduct extends Model
{
    protected $table = 'normalized_products';

    protected $attributes = [
        'isGreen' => false,
    ];

    protected $fillable = [
        'source',
        'subsource',
        'source_id',
        'parent_category',
        'category',
        'default_customization_technique',
        'default_customization_position',
        'default_customization_dimension',
        'default_customization_max_colors',
        'main_variant_id',
        'supplier_info', // text
        'isGreen', // boolean
        'isPromo', // boolean
        'last_seen_active',
    ];

    public function variants(): HasMany
    {
        return $this->HasMany(NormalizedProductVariant::class, 'product_id');
    }

    public function active_variants(): HasMany
    {
        return $this->hasMany(NormalizedProductVariant::class, 'product_id')->where('last_seen_active', '>', Carbon::now()->subDays(3)->toDateString());
    }

    public function main_variant()
    {
        $main_variant = NormalizedProductVariant::find($this->main_variant_id);

        return $main_variant ?? $this->variants()->first();
    }

    public function size_variants(): HasMany
    {
        return $this->hasMany(NormalizedProductVariant::class, 'product_id')->groupBy('size');
    }

    public function customizations(): HasMany
    {
        return $this->HasMany(\App\Models\Customizations\Customization::class, 'normalized_product_id');
    }

    public function app_product(): HasOne
    {
        return $this->HasOne(\App\Models\Product::class, 'source_sku', 'source_id');
    }
}
