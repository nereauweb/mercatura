<?php

namespace App\Models\ImportData;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VariantPrinting extends Model
{
    protected $table = 'printing_variants';

    protected $fillable = [
        'source',
        'pipeline',
        'source_product_sku',
        'normalized_product_id',
        'product_id',
        'source_variant_sku',
        'normalized_variant_id',
        'variant_id',
        'technique_label',
        'position_label',
        'position_code',
        'technique_main_code',
        'image',
        'is_default',
        'processing_days',
        'has_packaging',
        'minimum_quantity',
        'max_colors',
        'max_print_position',
        'packaging_code',
    ];

    /** @return HasMany<VariantPrintingSize, $this> */
    public function printing_sizes(): HasMany
    {
        return $this->HasMany(VariantPrintingSize::class, 'parent_id');
    }

    public function product(): BelongsTo
    {
        return $this->BelongsTo(\App\Models\Product::class, 'product_id');
    }

    public function normalized_product(): BelongsTo
    {
        return $this->BelongsTo(NormalizedProduct::class, 'normalized_product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->BelongsTo(\App\Models\ProductVariant::class, 'variant_id');
    }

    public function normalized_variant(): BelongsTo
    {
        return $this->BelongsTo(NormalizedProductVariant::class, 'normalized_variant_id');
    }

    public function max_colours()
    {
        if (! $this->max_colors) {
            $printing_size = $this->printing_sizes()->first();
            $this->max_colors = (string) $printing_size->printing_colors()->count();
            $this->save();
        }

        return $this->max_colors;
    }

    public function default_print_size()
    {
        $printing_size = $this->printing_sizes()->first();

        return $printing_size->label;
    }
}
