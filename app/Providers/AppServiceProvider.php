<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Payment SDK keys are read by the drivers under app/Drivers/Payment (config/cashier.php, config/paypal.php).
    }

    /**
     * Bootstrap any application services.
     */
    /**
     * Navigation data shared with every view: computed lazily on the first
     * render (never at boot, so artisan commands and migrations run on an
     * empty database) and cached.
     */
    public function boot(): void
    {
        foreach ([
            \App\Models\Product::class, \App\Models\ProductVariant::class, \App\Models\Category::class, \App\Models\Brand::class,
            \App\Models\ProductColor::class, \App\Models\ProductColorFamily::class, \App\Models\ProductSize::class,
            \App\Models\ProductSizeType::class, \App\Models\ProductAttribute::class,
        ] as $model) {
            Gate::policy($model, \App\Policies\CatalogPolicy::class);
        }
        Gate::policy(\App\Models\Customizations\Customization::class, \App\Policies\CatalogPolicy::class);
        foreach ([\App\Models\Page::class, \App\Models\ContentHomeSlide::class, \App\Models\BlogArticle::class, \App\Models\BlogTag::class, \App\Models\LegacyRedirect::class] as $model) {
            Gate::policy($model, \App\Policies\ContentPolicy::class);
        }

        $shared = null;
        View::composer('*', function (\Illuminate\View\View $view) use (&$shared): void {
            $shared ??= self::sharedNavigation();
            $view->with($shared);
        });
    }

    /**
     * @return array{nav_categories: array<int, array<string, mixed>>, nav_extra_pages: array<int, array<string, mixed>>, nav_utility_pages: array<int, array<string, mixed>>, all_brands: \Illuminate\Support\Collection<int, string>}
     */
    public static function sharedNavigation(): array
    {
        $categories = Cache::remember('categories', now()->addDays(1), function () {
            $loaded_categories = [];
            $db_categories = Category::whereNull('parent_id')->orderBy('position')->get();
            foreach ($db_categories as $db_category) {
                $children = [];
                foreach ($db_category->ordered_children as $child) {
                    array_push($children, [
                        'id' => $child->id,
                        'icon' => $child->icon,
                        'name' => $child->name,
                        'slug' => $child->slug(),
                    ]);
                }
                $main_cat = [
                    'id' => $db_category->id,
                    'icon' => $db_category->icon,
                    'name' => $db_category->name,
                    'slug' => $db_category->slug(),
                    'description' => $db_category->description,
                    'children' => $children,
                ];
                array_push($loaded_categories, $main_cat);
            }

            return $loaded_categories;
        });

        $nav_extra_pages = Cache::remember('nav_extra_pages', now()->addHour(1), function () {
            return Page::where('navbar', 1)->get()->toArray();
        });
        // Pages shown in the header's utility bar (next to Contacts): the ones flagged "topbar" in the
        // admin, by position, plus the slugs an installation lists in config brand.utility_pages (legacy).
        $utilitySlugs = (array) config('brand.utility_pages', []);
        $nav_utility_pages = Cache::remember('nav_utility_pages_'.md5(implode(',', $utilitySlugs)), now()->addHour(), function () use ($utilitySlugs) {
            $flagged = Page::query()->where('topbar', 1)->where('active', 1)->orderBy('position')->orderBy('id')->get(['slug', 'title']);
            $listed = $utilitySlugs === [] ? collect() : Page::query()->whereIn('slug', $utilitySlugs)->where('active', 1)->get(['slug', 'title'])->keyBy('slug');
            $pages = [];
            foreach ($flagged as $page) {
                $pages[$page->slug] = ['slug' => $page->slug, 'title' => $page->title];
            }
            foreach ($utilitySlugs as $slug) {
                if ($listed->has($slug) && ! isset($pages[$slug])) {
                    $pages[$slug] = ['slug' => $slug, 'title' => $listed[$slug]->title];
                }
            }

            return array_values($pages);
        });
        $utilityInBar = array_column($nav_utility_pages, 'slug');
        $nav_extra_pages = array_values(array_filter($nav_extra_pages, fn (array $page) => ! in_array($page['slug'], $utilityInBar, true)));

        $brands = Cache::remember('all_brands', now()->addDays(3), function () {
            return Product::select('brand')->distinct()->where('active', 1)->whereNot('brand', 'Unbranded')->whereNot('brand', '0')->pluck('brand');
        });

        return ['nav_categories' => $categories, 'nav_extra_pages' => $nav_extra_pages, 'nav_utility_pages' => $nav_utility_pages, 'all_brands' => $brands];
    }
}
