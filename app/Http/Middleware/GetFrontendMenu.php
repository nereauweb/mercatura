<?php

namespace App\Http\Middleware;

use App;
use App\Models\Category;
use Closure;
use Illuminate\Support\Facades\Cache;

class GetFrontendMenu	// NOT IN USE, se app\Providers\AppServiceProvider instead
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $categories = Cache::remember('categories', 3600, function () {
            $loaded_categories = [];
            $db_categories = Category::with('children')->whereNull('parent_id')->orderBy('position')->get();
            foreach ($db_categories as $db_category) {
                $children = [];
                foreach ($db_category->ordered_children as $child) {
                    array_push($children, [
                        'id' => $child->id,
                        'icon' => $child->icon,
                        'name' => $child->name,
                        'slug' => $child->slug,
                    ]);
                }
                $main_cat = [
                    'id' => $db_category->id,
                    'icon' => $db_category->icon,
                    'name' => $db_category->name,
                    'slug' => $db_category->slug,
                    'children' => $children,
                ];
                array_push($loaded_categories, $main_cat);
            }
        });

        view()->share('nav_categories', $categories);

        return $next($request);
    }
}
