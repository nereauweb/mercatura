<?php

namespace App\Models;

use App\Models\Concerns\HasSeoFields;
use App\Models\Concerns\RedirectsOldSlugs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Page extends Model
{
    use HasSeoFields;
    use RedirectsOldSlugs;

    protected $casts = ['active' => 'boolean', 'navbar' => 'boolean', 'topbar' => 'boolean', 'products' => 'boolean', 'noindex' => 'boolean', 'position' => 'integer'];

    public static function pathForSlug(string $slug): string
    {
        return '/contenuti/'.$slug;
    }

    protected $table = 'pages';

    protected $fillable = [
        'title',
        'title_color',
        'text',
        'cta_text',
        'cta_link',
        'cover',
        'products',
        'raw_content',
        'extra_text',
        'active',
        'navbar',
        'topbar',
        'position',
        'slug',
        'seo_title', // string
        'seo_description', // string
        'canonical_url',
        'noindex',
        'og_title',
        'og_description',
        'og_image',
    ];

    /** @return HasMany<PageContent, $this> */
    public function contents(): HasMany
    {
        return $this->hasMany(PageContent::class, 'page_id', 'id');
    }

    /** @return HasMany<PageContent, $this> */
    public function contents_products(): HasMany
    {
        return $this->hasMany(PageContent::class, 'page_id', 'id')->where('filter_type', 'product_id');
    }

    public function products_ids($debug = false)
    {

        if ($this->contents()->first()->filter_type == 'category_id') {
            return $this->contents()->first()->category_products();
        }

        $product_ids_query = Product::where('active', 1)
            ->when($this->created_after_filter_value() != '', function ($q) {
                return $q->where('created_at', '>', date('Y-m-d H:i:s', strtotime($this->created_after_filter_value())));
            })
            ->when($this->attribute_filters, function ($q) {
                foreach ($this->attribute_filters as $attribute_filter) {
                    $q->whereHas('variant_attributes', function ($sq) use ($attribute_filter) {
                        $sq->where('attribute_id', $attribute_filter->filter_target)
                            ->where('value', 'LIKE', '%'.$attribute_filter->filter_value.'%');
                    });
                }

                return $q;
            })
            ->when($this->is_sale_filter_value() > 0, function ($q) {
                return $q->whereHas('main_variant_relationship', function ($sq) {
                    $sq->where('isSale', 1);
                });
            })
            ->when($this->is_bestseller_filter_value() == 1, function ($q) {
                return $q->where('isBestseller', 1);
            })
            ->when($this->is_promo_filter_value() > 0, function ($q) {
                return $q->where('isPromo', 1);
            })
            ->when($this->is_green_filter_value() > 0, function ($q) {
                return $q->where('isGreen', 1);
            });
        /*
        ->when(!empty($this->brands), function ($q) {
            return $q->whereHas('variant_attributes', function($sq) {
                $sq->where('attribute_id',3)
                    ->whereIn('value', $this->brands);
            });
        })
        ->when($this->min_price != 0 || $this->max_price != 10000000, function ($q) {
            return $q->whereHas('variants', function($sq) {
                return $sq
                    ->whereHas('highestPrice', function($query){
                        $query->where('price', '<', $this->max_price);
                    })
                    ->whereHas('lowestPrice', function($query){
                        $query->where('price', '>', $this->min_price);
                    });
            });
        })
        */
        if ($debug) {
            return $product_ids_query->toSql();
        }
        $product_ids = $product_ids_query->pluck('id');

        return $product_ids;

    }

    /** @return HasMany<PageContent, $this> */
    public function category_filters(): HasMany
    {
        return $this->hasMany(PageContent::class, 'page_id', 'id')->where('filter_type', 'category_id');
    }

    /** @return HasOne<PageContent, $this> */
    public function created_after_filter(): HasOne
    {
        return $this->hasOne(PageContent::class, 'page_id', 'id')->where('filter_type', 'created_after_value');
    }

    public function created_after_filter_value()
    {
        $filter = $this->created_after_filter;

        return $filter ? $filter->filter_value : '';
    }

    /** @return HasOne<PageContent, $this> */
    public function is_sale_filter(): HasOne
    {
        return $this->hasOne(PageContent::class, 'page_id', 'id')->where('filter_type', 'is_sale');
    }

    public function is_sale_filter_value()
    {
        $filter = $this->is_sale_filter;

        return $filter ? $filter->filter_value : false;
    }

    /** @return HasOne<PageContent, $this> */
    public function is_bestseller_filter(): HasOne
    {
        return $this->hasOne(PageContent::class, 'page_id', 'id')->where('filter_type', 'is_bestseller');
    }

    public function is_bestseller_filter_value()
    {
        $filter = $this->is_bestseller_filter;

        return $filter ? $filter->filter_value : false;
    }

    /** @return HasOne<PageContent, $this> */
    public function is_promo_filter(): HasOne
    {
        return $this->hasOne(PageContent::class, 'page_id', 'id')->where('filter_type', 'is_promo');
    }

    public function is_promo_filter_value()
    {
        $filter = $this->is_promo_filter;

        return $filter ? $filter->filter_value : false;
    }

    /** @return HasOne<PageContent, $this> */
    public function is_green_filter(): HasOne
    {
        return $this->hasOne(PageContent::class, 'page_id', 'id')->where('filter_type', 'is_green');
    }

    public function is_green_filter_value()
    {
        $filter = $this->is_green_filter;

        return $filter ? $filter->filter_value : false;
    }

    /** @return HasMany<PageContent, $this> */
    public function attribute_filters(): HasMany
    {
        return $this->hasMany(PageContent::class, 'page_id', 'id')->where('filter_type', 'attribute_id_value');
    }

    public function contents_filter_attribute($id)
    {
        $filter = $this->attribute_filters()->where('filter_target', $id)->first();

        return $filter ? $filter->filter_value : '';
    }
}
