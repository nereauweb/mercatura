<?php

namespace App\Models\ImportData;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'default_print_technique',
        'default_print_position',
        'default_print_dimension',
        'default_print_max_colors',
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

    public function printing_positions(): BelongsToMany
    {
        return $this->belongsToMany(NormalizedPrintingPosition::class)->using(NormalizedProductPrintingPosition::class)->withPivot('image', 'ref', 'is_default');
    }

    public function printings(): HasMany
    {
        return $this->HasMany(\App\Models\ImportData\VariantPrinting::class, 'normalized_product_id');
    }

    public function app_product(): HasOne
    {
        return $this->HasOne(\App\Models\Product::class, 'source_sku', 'source_id');
    }
}
