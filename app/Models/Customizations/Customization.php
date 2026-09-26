<?php

declare(strict_types=1);

namespace App\Models\Customizations;

use App\Models\ImportData\NormalizedProduct;
use App\Models\ImportData\NormalizedProductVariant;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\ImportConnectors;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One decoration offered on a variant: a technique at a position, with its
 * areas (extent), options (the priced dimension: colours, threads…) and
 * quantity tiers below. Written by the connectors (docs/ARCHITECTURE.md §13)
 * or by the admin; `family` is optional and untyped (docs/03 decision 2).
 * Rows the admin creates carry a source no connector owns (`own`) and are
 * never touched by imports; rows received from an import are protected
 * only while `locked` is set (v2c.6). Connectors write through
 * ConnectorCommand::upsertCustomization() and prune through importable().
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
 * @property bool $locked
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
        'source', 'pipeline', 'family', 'locked', 'source_product_sku', 'normalized_product_id', 'product_id', 'source_variant_sku', 'normalized_variant_id', 'variant_id',
        'technique_label', 'position_label', 'position_code', 'technique_main_code', 'image', 'is_default', 'processing_days', 'has_packaging',
        'minimum_quantity', 'max_colors', 'max_print_position', 'packaging_code', 'supplier_data',
        'active', 'last_seen_at', 'source_hash',
    ];

    protected $casts = ['supplier_data' => 'array', 'locked' => 'boolean', 'active' => 'boolean', 'last_seen_at' => 'datetime'];

    public const MANUAL_SOURCE = 'own';

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

    /** Created in the admin, not by a connector: no connector owns its source. */
    public function isManual(): bool
    {
        return app(ImportConnectors::class)->forSource($this->source) === null;
    }

    /** Imports must neither update nor delete it: manual, or received from an import and locked. */
    public function isProtectedFromImport(): bool
    {
        return $this->locked || $this->isManual();
    }

    /**
     * The rows an import may update or delete: unlocked rows of a connector source.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeImportable(Builder $query): Builder
    {
        return $query->where('locked', false)->whereIn('source', app(ImportConnectors::class)->allSourceValues());
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
