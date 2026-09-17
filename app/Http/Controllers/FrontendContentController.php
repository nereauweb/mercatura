<?php

namespace App\Http\Controllers;

use App\Models\ContentHomeSlide;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class FrontendContentController extends Controller
{
    public function index(Request $request)
    {
        /*
        if ( Cache::has('homepage') ) {
            return Cache::get('homepage');
        }
*/
        $slides = Cache::remember('home_slides', now()->addDays(1), function () {
            return ContentHomeSlide::query()->where('active', 1)->orderBy('position')->get();
        });
        /*
        $bestsellers = Cache::remember('home_bestellers', now()->addDays(1), function () {
                    return Product::where('active',1)
                    ->where('isBestseller',1)
                    ->orWhere('isPromo',1)
                    ->inRandomOrder()
                    ->limit(16)
                    ->get();
                } );
        */
        $promo = Cache::remember('home_promo', now()->addDays(1), function () {
            return Product::where('active', 1)
                ->where('isPromo', 1)
                ->with(['color_variants.color', 'main_variant_relationship'])
                ->inRandomOrder()->limit(16)->get();
        });
        $green = Cache::remember('home_green', now()->addDays(1), function () {
            return Product::where('active', 1)
                ->where('isGreen', 1)
                ->with(['color_variants.color', 'main_variant_relationship'])
                ->inRandomOrder()->limit(16)->get();
        });
        $rendered_page = view('frontend.pages.home', compact('promo', 'green', 'slides'))->render();

        // Cache::put('homepage', $rendered_page, now()->addDays(1));
        return $rendered_page;
    }

    /*
    public function list (Request $request){
        $products = Product::all();
        return view('frontend.pages.list', compact('products'));
    }
    */

    public function page(Request $request, $slug)
    {
        $page = Page::where('slug', $slug)->first();
        if (! $page) {
            abort(404); // an unknown slug may have a stored redirect (Handler)
        }

        return view('frontend.pages.page', compact('page'));
    }

    /** robots.txt: the core rules, or "stay away" on a staging instance (config mercatura.staging). */
    public function robots()
    {
        $body = config('mercatura.staging.enabled')
            ? "User-agent: *\nDisallow: /\n"
            : view('frontend.public.robots')->render();

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function notFound(Request $request)
    {
        return response()->view('frontend.pages.not_found', [], 404);
    }

    public function error(Request $request, $statusCode = 500)
    {
        return response()->view('frontend.pages.error', ['statusCode' => $statusCode], $statusCode);
    }
}
