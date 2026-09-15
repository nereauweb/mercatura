<?php

namespace App\Models;

use App\Models\Concerns\HasSeoFields;
use App\Models\Concerns\RedirectsOldSlugs;
use App\Support\CanonicalUrl;
use App\Support\CaughtExceptionLogger;
use App\Support\Connectors\PrintingPipeline;
use App\Support\ImportConnectors;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Scout\Attributes\SearchUsingFullText;
use Laravel\Scout\Attributes\SearchUsingPrefix;
use Laravel\Scout\Searchable;

class Product extends Model
{
    use HasSeoFields;
    use RedirectsOldSlugs;
    use Searchable;
    use SoftDeletes;

    // isGreen/isPromo/isBestseller and isSale are not booleans: the import and the
    // legacy admin store forced values (-1, 2) in them. They stay integers until v2b.3.
    protected $casts = [
        'active' => 'boolean',
        'full_update' => 'boolean',
        'noindex' => 'boolean',
    ];

    public static function pathForSlug(string $slug): string
    {
        return '/prodotti/'.$slug;
    }

    protected $table = 'products';

    protected $attributes = [
        'isGreen' => false,
        'isBestseller' => false,
    ];

    protected $fillable = [
        'sku', // string
        'source', // connector key (or its legacy spelling) or 'own'
        'subsource',
        'source_sku',
        'active',
        'forced_status', // enum: 'none','active', 'disabled'
        'name', // string
        'description', // text
        'cover_url', // bigint unsigned
        'slug', // string
        'seo_title', // string
        'seo_description', // string
        'main_variant_id',  // bigint unsigned
        'variants_min_price',
        'variants_max_price',
        'variants_colors',
        'brand',
        'brand_image',
        'brand_id',
        'canonical_url',
        'noindex',
        'og_title',
        'og_description',
        'og_image',
        'supplier_info', // text
        'default_print_technique',
        'default_print_position',
        'full_update',
        'isGreen', // boolean
        'isPromo', // boolean
        'isBestseller', // boolean
    ];

    public $saved_printing_techniques_positions = [];

    public $saved_default_printing_position = null;

    public function searchableAs(): string
    {
        return 'products';
    }

    public function shouldBeSearchable(): bool
    {
        return $this->active ? true : false;
    }

    #[SearchUsingPrefix(['sku'])]
    #[SearchUsingFullText(['name', 'description'])]
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'variants_sku' => implode(',', $this->variants()->pluck('sku')->toArray()),
            'name' => $this->name,
            'description' => $this->description,
            'cover' => url('/').$this->cover(true),
            'link' => url('/').'/prodotti/'.$this->slug(),
            'price' => $this->formatted_min_price(),
        ];
    }

    // todo: implement cover
    public function cover($url = false, $regenerate = false, $regenerate_main_variant = false, $full_image = false)
    {
        if ($url && $this->cover_url && ! $regenerate && ! $full_image) {
            return $this->cover_url;
        }
        $main_variant = $this->main_variant($regenerate_main_variant);
        if (! $main_variant) {
            return false;
        }
        if ($url) {
            $cover = $main_variant->getFirstMedia('image');
            if (! $cover) {
                return false;
            }
            $this->cover_url = $full_image ? $cover->getUrl() : $cover->getUrl('thumb');
            $this->cover_url = str_replace(url('/'), '', $this->cover_url);
            $this->save();

            return $this->cover_url;
        }

        return $main_variant->getFirstMedia('image');
    }

    public function slug($regenerate = false)
    {
        if (! $this->slug || $regenerate) {
            /*
            $this->slug = str_replace(' ','-',\Normalizer::normalize( $this->name, \Normalizer::FORM_C )) . '-' . str_replace(' ','-',$this->sku);
            */
            $this->slug = urlencode(preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', $this->name).'-'.$this->sku));
            $this->save();
        }

        return $this->slug;
    }

    public function canonical_url(): string
    {
        return $this->resolvedCanonicalUrl(CanonicalUrl::absolute('/prodotti/'.$this->slug()));
    }

    /**
     * Ottiene le informazioni dell'immagine per Open Graph
     *
     * @return array|null Array con 'url', 'width', 'height', 'type' oppure null se l'immagine non è disponibile
     */
    public function getOpenGraphImageInfo()
    {
        $cover_path = $this->cover(true, false, false, true);
        if (! $cover_path) {
            return null;
        }

        // Genera URL assoluto HTTPS per l'immagine
        // Se il percorso inizia con /, usa secure_asset, altrimenti usa secure_url
        if (str_starts_with($cover_path, '/')) {
            $cover_url = secure_asset(ltrim($cover_path, '/'));
        } elseif (str_starts_with($cover_path, 'http://') || str_starts_with($cover_path, 'https://')) {
            // Se è già un URL completo, assicurati che sia HTTPS
            $cover_url = str_replace('http://', 'https://', $cover_path);
        } else {
            $cover_url = secure_url($cover_path);
        }
        $image_width = 1200;
        $image_height = 630;
        $image_type = 'image/jpeg';

        $main_variant = $this->main_variant();
        if ($main_variant) {
            $cover_media = $main_variant->getFirstMedia('image');
            if ($cover_media) {
                try {
                    $image_path = $cover_media->getPath();
                    if (file_exists($image_path)) {
                        $image_info = getimagesize($image_path);
                        if ($image_info) {
                            $image_width = $image_info[0];
                            $image_height = $image_info[1];
                            $image_type = $image_info['mime'];
                        }
                    }
                } catch (\Exception $e) {
                    CaughtExceptionLogger::error('Product::cover_metadata getimagesize failed', $e, [
                        'product_id' => $this->id,
                    ]);
                }
            }
        }

        return [
            'url' => $cover_url,
            'width' => $image_width,
            'height' => $image_height,
            'type' => $image_type,
        ];
    }

    /* CATEGORIES */

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->using(ProductCategory::class);
    }

    public function breadcrumb_categories()
    {
        $breadcrumb_categories = false;
        $first_category = $this->categories()->orderBy('parent_id', 'desc')->first();
        if ($first_category) {
            $breadcrumb_categories[] = $first_category;
            if ($first_category->parent_id) {
                $breadcrumb_categories[] = $first_category->parent_category()->first();
            }
        }

        return $breadcrumb_categories;
    }

    public function hasCategory($category)
    {
        return $this->categories->contains($category);
    }

    /* VARIANTS */

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id');
    }

    public function active_variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id')->where('active', '1');
    }

    /** @return HasMany<ProductVariant, $this> */
    public function color_variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id')->groupBy('color_id')->where('active', '1');
    }

    /** @return HasMany<ProductVariant, $this> */
    public function size_variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id')->groupBy('size_id')->where('active', '1');
    }

    public function size_variants_count()
    {
        return count($this->size_variants->toArray());
    }

    public function color_variants_stock($color_id)
    {
        return $this->variants()->where('active', '1')->where('color_id', $color_id)->sum('stock');
    }

    public function size_variants_stock($size_id)
    {
        return $this->variants()->where('active', '1')->where('size_id', $color_id)->sum('stock');
    }

    public function variant_by_color_and_size_stock($color_id, $size_id)
    {
        return $this->variants()->where('active', '1')->where('size_id', $color_id)->where('size_id', $color_id)->sum('stock');
    }

    public function all__variants_stock()
    {
        return $this->variants()->where('active', '1')->sum('stock');
    }

    // todo: implement order / main_variant_id
    public function main_variant($regenerate = false)
    {
        // If we have a main_variant_id, check if the variant exists and is active
        if ($this->main_variant_id) {
            $variant = ProductVariant::find($this->main_variant_id);
            // If variant exists and is active → use it (regenerate or not)
            if ($variant && $variant->active) {
                return $variant;
            }
            // Variant not found or not active → fall through to fallback
        }

        // Fallback: try to set another active variant (with media and prices) as main
        $result = $this->set_main_variant(false, true);

        return $result; // ProductVariant or null if no valid variant
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function main_variant_relationship(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'main_variant_id');
    }

    /** @return BelongsToMany<ProductAttribute, $this> */
    public function variant_attributes(): BelongsToMany
    {
        return $this->belongsToMany(ProductAttribute::class, 'products_variants_attributes', 'product_id', 'attribute_id')->withPivot('value');
    }

    /** @return BelongsTo<Brand, $this> */
    public function brandRecord(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function formatted_min_price()
    {
        return $this->variants_min_price && $this->variants_min_price != 0 ? number_format($this->variants_min_price, 2, ',', '.') : '-';
    }

    /**
     * Set the main variant and update price fields and cover
     *
     * @param  int|false  $new_main_variant_id  The new main variant ID, or false to choose randomly
     * @param  bool  $save  Whether to save the changes
     * @return ProductVariant|null The main variant instance
     */
    public function set_main_variant($new_main_variant_id = false, $save = true)
    {
        // If specific variant is provided, use it
        if ($new_main_variant_id !== false) {
            $this->main_variant_id = $new_main_variant_id;
        } else {
            // No specific variant provided, choose one randomly
            // Valid variants must be: active, have media, and have prices
            $valid_variants = $this->active_variants()
                ->whereHas('media')
                ->whereHas('prices')
                ->get();
            if ($valid_variants->isEmpty()) {
                // No valid variants found, reset everything
                $this->variants_min_price = 0;
                $this->variants_max_price = 0;
                $this->cover_url = null;
                $this->active = false;
                if ($save) {
                    $this->save();
                }

                return null;
            }

            // Choose a random variant from valid ones
            $variant = $valid_variants->random();
            $this->main_variant_id = $variant->id;
        }

        // Get the main variant to update prices and cover
        $main_variant = ProductVariant::find($this->main_variant_id);
        if (! $main_variant) {
            // Main variant not found, reset everything
            $this->variants_min_price = 0;
            $this->variants_max_price = 0;
            $this->cover_url = null;
            $this->active = false;
            if ($save) {
                $this->save();
            }

            return null;
        }

        // Get lowest and highest prices from main variant
        $lowest_price = $main_variant->lowestPrice;
        $highest_price = $main_variant->highestPrice;

        // Update price fields
        $this->variants_min_price = $lowest_price ? $lowest_price->price : 0;
        $this->variants_max_price = $highest_price ? $highest_price->price : 0;

        // Update cover from new main variant
        $this->cover(true, true, true); // regenerate cover from new main variant

        // Save changes
        if ($save) {
            $this->save();
        }

        return $main_variant;
    }

    /* LABEL ATTRIBUTES */

    public function isNew()
    {
        return strtotime($this->created_at) > strtotime('-1 month');
        /*
        return Cache::remember('product_' . $this->id . '_is_new', now()->addDays(1), function () { return strtotime($this->created_at) > strtotime('-1 week'); });
*/
    }

    public function isSale()
    {
        return Cache::remember('product_'.$this->id.'_is_sale', now()->addDays(1), function () {
            return $this->variants()->where('isSale', true)->exists();
        });
    }

    public function isBestseller(): bool
    {
        return (bool) ($this->attributes['isBestseller'] ?? false);
    }

    public function isGreen(): bool
    {
        return (bool) ($this->attributes['isGreen'] ?? false);
    }

    public function isPromo(): bool
    {
        return (bool) ($this->attributes['isPromo'] ?? false);
    }

    /*
    public function printing_positions(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\ImportData\NormalizedPrintingPosition::class)->using(NormalizedPrintingPositionProduct::class)->withPivot('image', 'ref', 'is_default')->orderByPivot('is_default', 'desc');
    }
    */

    public function default_printing_position()
    {
        $default_printing_position = $this->default_printing_position();
        if ($default_printing_position) {
            return $default_printing_position;
        }

        return null;
    }

    public function default_printing_position_technique_label()
    {
        $default_printing_position = $this->default_printing_position();
        if ($default_printing_position) {
            return $default_printing_position->printing_technique->label;
        }

        return '';
    }

    public function printing_techniques_positions()
    {
        if (empty($this->saved_printing_techniques_positions)) {
            $data = [];
            foreach ($this->printing_positions()->orderBy('label')->get() as $printing_position) {
                $data[$printing_position->printing_technique->label][$printing_position->label] = $printing_position->pivot->image ?? '';
            }
            $this->saved_printing_techniques_positions = $data;
        }

        return $this->saved_printing_techniques_positions;
    }

    //

    /** @return HasMany<\App\Models\ImportData\VariantPrinting, $this> */
    public function printings(): HasMany
    {
        $relation = $this->HasMany(\App\Models\ImportData\VariantPrinting::class, 'product_id');
        PrintingPipeline::apply($relation->getQuery());

        return $relation;
    }

    public function default_printing()
    {
        return $this->printings()->where('is_default', true)->first();
    }

    public function default_printing_technique_label()
    {
        return $this->default_printing() ? $this->default_printing()->technique_label : false;
    }

    /** Delivery days: the source connector's rule (or 7) plus the default printing's days. */
    public function processing_days($printing = 'default')
    {
        $connector = app(ImportConnectors::class)->forSource($this->source);
        $processing_days = $connector ? $connector->processingDays($this) : \App\Support\Connectors\BaseConnector::DEFAULT_PROCESSING_DAYS;
        if ($this->default_printing() && $printing == 'default') {
            $processing_days += $this->default_printing()->processing_days;
        }

        return $processing_days;
    }

    //
    /** Raw supplier data kept by the source connector, for the admin. @return array<string, mixed> */
    public function raw_import_data()
    {
        return app(ImportConnectors::class)->forSource($this->source)?->rawProductData($this) ?? [];
    }

    public function get_seo_title()
    {
        $title = strip_tags($this->seo_title ? $this->seo_title : $this->name);

        return Str::limit($title, 60);
    }

    public function get_seo_description()
    {
        $description = strip_tags($this->seo_description ? $this->seo_description : $this->description);

        return Str::limit($description, 120);
    }
}
