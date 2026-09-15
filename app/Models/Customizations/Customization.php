<?php

declare(strict_types=1);

namespace App\Models\Customizations;

use App\Models\ImportData\NormalizedProduct;
use App\Models\ImportData\NormalizedProductVariant;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One decoration offered on a variant: a technique at a position, with its
 * areas (extent), options (the priced dimension: colours, threads…) and
 * quantity tiers below. Written by the connectors (docs/ARCHITECTURE.md §13)
 * or by the admin; `family` is optional and untyped (docs/03 decision 2).
 *
 * @property int $id
 * @property string $source
 * @property string|null $pipeline
 * @property string|null $source_product_sku
 * @property string|null $source_variant_sku
 * @property int|null $normalized_product_id
 * @property int|null $normalized_variant_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $family
 * @property int|null $product_id
 * @property int|null $variant_id
 * @property string $technique_label
 * @property string $position_label
 * @property string|null $position_code
 * @property string|null $technique_main_code
 * @property string|null $image
 * @property int $is_default
 * @property int $processing_days
 * @property int $has_packaging
 * @property int $minimum_quantity
 * @property string|null $max_colors
 * @property int|null $max_print_position
 * @property string|null $packaging_code
 * @property array<string, mixed>|null $supplier_data
 */
class Customization extends Model
{
    protected $table = 'customizations';

    protected $fillable = [
        'source', 'pipeline', 'family', 'source_product_sku', 'normalized_product_id', 'product_id', 'source_variant_sku', 'normalized_variant_id', 'variant_id',
        'technique_label', 'position_label', 'position_code', 'technique_main_code', 'image', 'is_default', 'processing_days', 'has_packaging',
        'minimum_quantity', 'max_colors', 'max_print_position', 'packaging_code', 'supplier_data',
    ];

    protected $casts = ['supplier_data' => 'array'];

    /** @return HasMany<CustomizationArea, $this> */
    public function areas(): HasMany
    {
        return $this->hasMany(CustomizationArea::class, 'parent_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /** @return BelongsTo<NormalizedProduct, $this> */
    public function normalized_product(): BelongsTo
    {
        return $this->belongsTo(NormalizedProduct::class, 'normalized_product_id');
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /** @return BelongsTo<NormalizedProductVariant, $this> */
    public function normalized_variant(): BelongsTo
    {
        return $this->belongsTo(NormalizedProductVariant::class, 'normalized_variant_id');
    }

    /** Max colours as the supplier states it; backfilled from the first area's option count when missing (legacy behaviour). */
    public function maxColors(): ?string
    {
        if (! $this->max_colors) {
            $area = $this->areas()->first();
            $this->max_colors = $area ? (string) $area->options()->count() : null;
            $this->save();
        }

        return $this->max_colors;
    }

    public function defaultAreaLabel(): ?string
    {
        return $this->areas()->first()?->label;
    }
}
