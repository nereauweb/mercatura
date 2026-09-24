<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\CanonicalUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class FrontendListController extends Controller
{
    // by id
    public function index(Request $request, $id)
    {
        return $this->list_page(Category::find($id));
    }

    // by slug
    public function show(Request $request, $slug)
    {
        $category = Category::where('slug', $slug)->first();
        if (! $category) {
            abort(404); // an unknown slug may have a stored redirect (Handler); the full listing lives at /prodotti
        }

        return $this->list_page($category);
    }

    // by brand
    public function brand(Request $request, $brand)
    {
        return $this->list_page(false, $brand);
    }

    // prepare variables and render page
    public function list_page($category = false, $selected_brand = false)
    {

        if ($category) {
            $categories_ids_filter[] = $category->id;
            /*
            if ($category->children){
                $categories_ids_filter = array_merge($categories_ids_filter,$category->children()->pluck('id')->toArray());
            }
            */
            $product_ids = Product::select('id')->where('active', 1)->whereHas('categories', function ($q) use ($categories_ids_filter) {
                $q->whereIn('categories.id', $categories_ids_filter);
            })->pluck('products.id')->toArray();
        } elseif ($selected_brand) {
            /*
            $product_ids = Product::whereHas('variant_attributes', function($q) use ($selected_brand) {
                    $q->where('attribute_id',3)
                        ->where('value', $selected_brand);
                })
            */
            $product_ids = Product::where('active', 1)->where('brand', 'LIKE', '%'.$selected_brand.'%')
                ->pluck('products.id')->toArray();
        } else {
            $product_ids = Product::where('active', 1)->inRandomOrder()->pluck('products.id')->toArray();
        }

        if ($category) {
            $bestsellers = Cache::remember('cat_'.$category->id.'_bestellers', now()->addDays(1), function () use ($product_ids) {
                return Product::whereIn('id', $product_ids)->where('active', 1)->where('isBestseller', 1)->with(['color_variants.color', 'main_variant_relationship'])->inRandomOrder()->limit(16)->get();
            });
        } elseif ($selected_brand) {
            $bestsellers = Cache::remember('brand_'.$selected_brand.'_bestellers', now()->addDays(1), function () use ($selected_brand) {
                /*
                return Product::whereHas('variant_attributes', function($q) use ($selected_brand) {
                        $q->where('attribute_id',3)
                        ->where('value', 'LIKE', $selected_brand);
                    })->whereHas('variant_attributes', function($sq) {
                        $sq->where('attribute_id',18)
                        ->where('value', 'LIKE', '%bestseller%');
                    })->inRandomOrder()->limit(16)->get();
                */
                return Product::where('brand', $selected_brand)
                    ->where('active', 1)->where('isBestseller', 1)->with(['color_variants.color', 'main_variant_relationship'])->inRandomOrder()->limit(16)->get();
            });
        } else {
            $bestsellers = Cache::remember('brand_'.$selected_brand.'_bestellers', now()->addDays(1), function () {
                return Product::where('active', 1)->where('isBestseller', 1)->with(['color_variants.color', 'main_variant_relationship'])->inRandomOrder()->limit(16)->get();
            });
        }

        $count = count($product_ids);

        // Page identity and SEO
        if ($category) {
            $page_title = $category->get_seo_title();
            $page_description = $category->get_seo_description();
            $canonical = $category->canonical_url();
            $noindex = false;
        } elseif ($selected_brand) {
            $page_title = $selected_brand;
            $page_description = __('frontend.catalog.brand_description', ['brand' => $selected_brand, 'site' => config('brand.name')]);
            $canonical = CanonicalUrl::absolute('/prodotti/marchi/'.rawurlencode($selected_brand));
            $noindex = false;
        } else {
            $page_title = __('frontend.catalog.all_products');
            $page_description = __('frontend.catalog.all_products_description', ['site' => config('brand.name')]);
            $canonical = CanonicalUrl::absolute('/prodotti');
            $noindex = true;
        }

        // Breadcrumb: ordine corretto root -> ... -> pagina corrente (position 1 = primo nella navigazione)
        $breadcrumbs = [['name' => config('brand.name'), 'url' => CanonicalUrl::absolute('/')]];
        if ($category) {
            if ($category->parent_category) {
                $breadcrumbs[] = ['name' => $category->parent_category->name, 'url' => $category->parent_category->canonical_url()];
            }
            $breadcrumbs[] = ['name' => $category->name, 'url' => $canonical];
        } elseif ($selected_brand) {
            $breadcrumbs[] = ['name' => $selected_brand, 'url' => $canonical];
        } else {
            $breadcrumbs[] = ['name' => __('frontend.catalog.all_products'), 'url' => $canonical];
        }
        $breadcrumbStructuredData = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($breadcrumbs)->map(fn ($crumb, $index) => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'],
            ])->values()->all(),
        ];

        return view('frontend.pages.list', compact('count', 'category', 'selected_brand', 'bestsellers', 'page_title', 'page_description', 'canonical', 'noindex', 'breadcrumbs', 'breadcrumbStructuredData'));

    }

    public function get_variant_cover(Request $request, $id)
    {
        $variant = ProductVariant::query()->find($id);
        if ($variant === null) {
            abort(404);
        }

        return $variant->cover(true) ?: '';
    }
}
