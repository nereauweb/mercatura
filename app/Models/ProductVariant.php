<?php

namespace App\Models;

use App\Support\CaughtExceptionLogger;
use App\Support\Connectors\CustomizationPipeline;
use App\Support\Connectors\MarkupRules;
use App\Support\ImportConnectors;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property-read \App\Models\Customizations\Customization|null $highestMinimumCustomization
 */
class ProductVariant extends Model implements HasMedia
{
    use InteractsWithMedia;
    use SoftDeletes;

    protected $casts = [
        'active' => 'boolean',
        'stock' => 'integer',
        'next_stock_quantity' => 'integer',
    ];

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(150)
            ->height(150)
            ->nonQueued();
    }

    protected $table = 'products_variants';

    protected $fillable = [
        'product_id', // bigint unsigned
        'sku',
        'active',
        'isSale', // boolean
        'color_id', // bigint unsigned
        'size_id', // bigint unsigned
        'stock',
        'next_stock_date',
        'next_stock_quantity',
        'source',
        'source_sku',
    ];

    /** Fallback size id ("one size") when a variant is saved without one; see mercatura.catalog.one_size_id. */
    public const DEFAULT_UNIQUE_SIZE_ID = 52;

    protected static function booted(): void
    {
        static::saving(function (self $variant) {
            if ($variant->size_id === null) {
                $variant->size_id = (int) config('mercatura.catalog.one_size_id', self::DEFAULT_UNIQUE_SIZE_ID);
            }
        });
    }

    public static function nextId()
    {
        return static::max('id') + 1;
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /** @return BelongsTo<ProductColor, $this> */
    public function color(): BelongsTo
    {
        return $this->belongsTo(ProductColor::class, 'color_id');
    }

    /** @return BelongsTo<ProductSize, $this> */
    public function size(): BelongsTo
    {
        return $this->belongsTo(ProductSize::class, 'size_id');
    }

    public function cover($thumb_url = false)
    {
        $images = $this->getMedia('image');
        if (! isset($images) || ! isset($images[0]) || empty($images)) {
            return false;
        }
        if ($thumb_url) {
            return $images[0]->getUrl('thumb');
        }

        return $images[0]->getUrl();
    }

    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany('App\Models\ProductAttribute', 'products_variants_attributes', 'variant_id', 'attribute_id')->using(ProductAttributeVariantValue::class)->withPivot('value');
    }

    public function attribute($id)
    {
        $attribute_query = DB::table('products_variants_attributes')
            ->where('products_variants_attributes.attribute_id', $id)
            ->where('products_variants_attributes.variant_id', $this->id)
            ->first();

        return $attribute_query;
    }

    public function attribute_value($id)
    {
        $attribute = $this->attribute($id);
        if ($attribute) {
            return $attribute->value;
        }

        return false;
    }

    /** @return HasMany<ProductVariantPrice, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany('App\Models\ProductVariantPrice', 'variant_id');
    }

    public function ordered_prices($order = 'asc')
    {
        return $this->prices()->orderBy('from_quantity', $order)->get();
    }

    public function min_quantity()
    {
        // return $this->prices()->orderBy('price','DESC')->first()->price;
        $lowestQuantity = $this->lowestQuantity;
        if (! $lowestQuantity) {
            return 0;
        }

        return $lowestQuantity->from_quantity;
    }

    public function minCustomizationQuantity()
    {
        $default_printing = $this->defaultCustomization();
        if ($default_printing) {
            if ($default_printing->minimum_quantity > 0) {
                return $default_printing->minimum_quantity;
            }
        }

        return 1;
    }

    /** @return HasOne<ProductVariantPrice, $this> */
    public function lowestQuantity(): HasOne
    {
        return $this->hasOne(ProductVariantPrice::class, 'variant_id')->ofMany('from_quantity', 'min');
    }

    public function highestQuantity()
    {
        return $this->stock + $this->next_stock_quantity;
    }

    /** @return HasOne<ProductVariantPrice, $this> */
    public function lowestPrice(): HasOne
    {
        return $this->hasOne(ProductVariantPrice::class, 'variant_id')->ofMany('price', 'min');
    }

    public function min_price()
    {
        return $this->lowestPrice->price ?? 0;
    }

    /** @return HasOne<ProductVariantPrice, $this> */
    public function highestPrice(): HasOne
    {
        return $this->hasOne(ProductVariantPrice::class, 'variant_id')->ofMany('price', 'max');
    }

    public function max_price()
    {
        return $this->highestPrice->price ?? 0;
    }

    public function get_markup_percent($quantity, $original_price = false)
    {
        if (! $original_price) {
            $original_price = 0;
            foreach ($this->prices()->orderBy('from_quantity', 'desc')->get() as $price) {
                if (intval($price->from_quantity) <= intval($quantity)) {
                    $original_price = $price->original_price;
                    break;
                }
            }
        }

        return app(MarkupRules::class)->percent((float) $quantity * (float) $original_price, $this->source, $this->sku);
    }

    public function price_per_quantity($quantity, $use_original_price = false, $markup_percent = false, $with_default_printing = false, $with_default_princing_setup = false)
    {
        $original_price = 0;
        foreach ($this->prices()->orderBy('from_quantity', 'desc')->get() as $price) {
            if (intval($price->from_quantity) <= intval($quantity)) {
                // return floatval($use_original_price ? $price->original_price : $price->price);
                $original_price = $price->original_price;
                if ($use_original_price) {
                    return $original_price;
                }
                if (! $markup_percent) {
                    $markup_percent = $this->get_markup_percent($quantity, $original_price);
                }

                $default_print_color = null;
                if ($with_default_printing) {
                    $default_printing = $this->defaultCustomization();
                    if (! $default_printing) {
                        Log::warning('ProductVariant price_per_quantity: skipped default printing add-on', [
                            'reason' => 'no_printing',
                            'variant_id' => $this->id,
                            'sku' => $this->sku,
                            'quantity' => $quantity,
                        ]);
                    } else {
                        $default_area = $default_printing->areas()->first();
                        if (! $default_area) {
                            Log::warning('ProductVariant price_per_quantity: skipped default printing add-on', [
                                'reason' => 'no_print_size',
                                'variant_id' => $this->id,
                                'sku' => $this->sku,
                                'printing_id' => $default_printing->id,
                                'quantity' => $quantity,
                            ]);
                        } else {
                            $default_print_color = $default_area->options()->first();
                            if (! $default_print_color) {
                                Log::warning('ProductVariant price_per_quantity: skipped default printing add-on', [
                                    'reason' => 'no_print_color',
                                    'variant_id' => $this->id,
                                    'sku' => $this->sku,
                                    'printing_size_id' => $default_area->id,
                                    'quantity' => $quantity,
                                ]);
                            } else {
                                try {
                                    $default_print_costs = $default_print_color->priceFor($quantity, $quantity, $with_packaging = false, $use_original_price = true);
                                    $original_price += $default_print_costs['unit_price'];
                                } catch (\Throwable $e) {
                                    CaughtExceptionLogger::error('ProductVariant::price_per_quantity priceFor failed', $e, [
                                        'reason' => 'print_price_failed',
                                        'variant_id' => $this->id,
                                        'sku' => $this->sku,
                                        'printing_color_id' => $default_print_color->id,
                                        'quantity' => $quantity,
                                    ]);
                                }
                            }
                        }
                    }
                }
                $markup = round($original_price * ($markup_percent / 100), 2);
                if ($with_default_princing_setup && $default_print_color) {
                    return $original_price + $markup + ($default_print_color->setup / $quantity);
                }

                return $original_price + $markup;
            }
        }

        return 0;
    }

    public function additional_unit_costs_per_quantity($quantity)
    {
        foreach ($this->prices()->orderBy('from_quantity', 'desc')->get() as $price) {
            if (intval($price->from_quantity) <= intval($quantity)) {
                return $price->included_additional_costs;
            }
        }

        return 0;
    }

    public function defaultCustomization()
    {
        $default_printing = $this->customizations()->where('is_default', true)->first();
        if ($default_printing) {
            return $default_printing;
        }

        return $this->customizations()->first();
    }

    /** @return HasMany<\App\Models\Customizations\Customization, $this> */
    public function customizations(): HasMany
    {
        $relation = $this->HasMany(\App\Models\Customizations\Customization::class, 'variant_id');
        CustomizationPipeline::apply($relation->getQuery());

        return $relation;
    }

    public function maxCustomizationMinimumQuantity()
    {
        return $this->highestMinimumCustomization->minimum_quantity;
    }

    /** @return HasOne<\App\Models\Customizations\Customization, $this> */
    public function highestMinimumCustomization(): HasOne
    {
        $relation = $this->hasOne(\App\Models\Customizations\Customization::class, 'variant_id')->ofMany('minimum_quantity', 'max');
        CustomizationPipeline::apply($relation->getQuery());

        return $relation;
    }

    /** Raw supplier data kept by the source connector, for the admin. @return array<string, mixed> */
    public function raw_import_data()
    {
        return app(ImportConnectors::class)->forSource($this->source)?->rawVariantData($this) ?? [];
    }

    public function normalized_variant()
    {
        return \App\Models\ImportData\NormalizedProductVariant::where('source_id', $this->source_sku)->orderBy('last_seen_active', 'desc')->first();
    }

    /** Dimensions label from the source connector, or false when none. */
    public function dimensions()
    {
        return app(ImportConnectors::class)->forSource($this->source)?->variantDimensions($this) ?? false;
    }

    /* LABEL ATTRIBUTES */

    public function isNew()
    {
        return strtotime($this->created_at) > strtotime('-1 month');
    }

    public function isSale()
    {
        return $this->isSale == 1 || $this->isSale == 2 ? true : false;
    }
}
