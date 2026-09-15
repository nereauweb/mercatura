<?php

namespace App\Models;

use App\Models\Concerns\HasSeoFields;
use App\Models\Concerns\RedirectsOldSlugs;
use App\Support\CanonicalUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasSeoFields;
    use RedirectsOldSlugs;
    use SoftDeletes;

    protected $casts = ['active' => 'boolean', 'noindex' => 'boolean', 'position' => 'integer'];

    public static function pathForSlug(string $slug): string
    {
        return '/categorie/'.$slug;
    }

    protected $table = 'categories';

    protected $fillable = [
        'parent_id',
        'icon',
        'icon_rev',
        'name',
        'description',
        'extra_text',
        'position',
        'active',
        'slug',
        'seo_title', // string
        'seo_description', // string
        'canonical_url',
        'noindex',
        'og_title',
        'og_description',
        'og_image',
    ];

    public $min_products_price;

    public $max_products_price;

    /** @return HasMany<CategoryImportAlias, $this> */
    public function aliases(): HasMany
    {
        return $this->hasMany(CategoryImportAlias::class, 'category_id', 'id');
    }

    /** @return BelongsTo<Category, $this> */
    public function parent_category(): BelongsTo
    {
        return $this->BelongsTo(Category::class, 'parent_id', 'id');
    }

    /** @return HasMany<Category, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id', 'id');
    }

    /** @return HasMany<Category, $this> */
    public function siblings(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id', 'parent_id');
    }

    /** @return HasMany<Category, $this> */
    public function ordered_children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id', 'id')->orderBy('position', 'asc');
    }

    /** @return BelongsToMany<Product, $this, ProductCategory> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->using(ProductCategory::class)->where('products.active', 1);
    }

    /** @return BelongsToMany<Product, $this, ProductCategory> */
    public function ordered_products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->using(ProductCategory::class)->where('products.active', 1)->orderBy('category_product.position');
    }

    public function slug()
    {
        if (! $this->slug) {
            $this->slug = str_replace(' ', '-', \Normalizer::normalize($this->name, \Normalizer::FORM_C));
            $this->save();
        }

        return $this->slug;
    }

    public function canonical_url(): string
    {
        return $this->resolvedCanonicalUrl(CanonicalUrl::absolute('/categorie/'.$this->slug()));
    }

    public function get_products_min_max_prices($format = false)
    {
        // Use cached values if available
        if ($this->min_products_price && $this->max_products_price) {
            $min_price = $this->min_products_price;
            $max_price = $this->max_products_price;
        } else {
            // Build category IDs array (current category + children)
            $categories_ids = [$this->id];
            if ($this->children()->exists()) {
                $categories_ids = array_merge($categories_ids, $this->children()->pluck('id')->toArray());
            }

            // Single query to get min and max prices using joins
            $price_range = Product::selectRaw('MIN(products.variants_min_price) as min_price, MAX(products.variants_max_price) as max_price')
                ->join('category_product', 'products.id', '=', 'category_product.product_id')
                ->whereIn('category_product.category_id', $categories_ids)
                ->where('products.active', 1)
                ->whereNotNull('products.variants_min_price')
                ->where('products.variants_min_price', '>', 0)
                ->first();

            $min_price = $price_range->min_price ?? 0;
            $max_price = $price_range->max_price ?? 100;

            // Cache the results
            $this->min_products_price = $min_price;
            $this->max_products_price = $max_price;
        }

        if ($format) {
            return [
                $min_price ? number_format($min_price, 2, ',', '.') : '-',
                $max_price ? number_format($max_price, 2, ',', '.') : '-',
            ];
        }

        return [$min_price, $max_price];
    }

    public function get_products_colors()
    {
        $categories_ids[] = $this->id;
        // TODO!!!! problema categorie figlie si / no
        if ($this->children) {
            $categories_ids = array_merge($categories_ids, $this->children()->pluck('id')->toArray());
        }
        $products_ids = ProductCategory::whereIn('category_id', $categories_ids)->pluck('product_id')->toArray();
        // $variants_ids = ProductVariant::whereIn('product_id',$products_ids)->pluck('id')->toArray();
        $variants = ProductVariant::whereIn('product_id', $products_ids);
        $color_ids = [];
        foreach ($variants as $variant) {
            if (! in_array($variant->color_id, $color_ids)) {
                array_push($color_ids, $variant->color_id);
            }
        }
        $colors = ProductColor::whereIn('id', $color_ids);
    }

    public function get_seo_title()
    {
        return strip_tags($this->seo_title ? $this->seo_title : $this->name);
    }

    public function get_seo_description()
    {
        return strip_tags($this->seo_description ? $this->seo_description : $this->description);
    }
}
