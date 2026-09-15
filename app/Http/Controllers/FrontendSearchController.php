<?php

namespace App\Http\Controllers;

use App\Contracts\SearchEngine;
use App\Models\Product;
use Illuminate\Http\Request;

class FrontendSearchController extends Controller
{
    public function get_products(Request $request)
    {
        $products = [];
        $products_data = Product::limit(10)->where('name', 'LIKE', '%'.$request->terms.'%')->orWhere('sku', 'LIKE', '%'.$request->terms.'%')->get();
        foreach ($products_data as $product) {
            $cover = $product->cover();
            $products[] = [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'cover' => $cover ? $cover->getUrl() : '',
                'slug' => $product->slug(),
                'price' => $product->formatted_min_price(),
            ];
        }

        return $products;
    }

    /**
     * Header search suggestions from the configured SearchEngine
     * (mercatura.providers.search), rendered by the browser from this JSON.
     */
    public function suggest(string $terms): \Illuminate\Http\JsonResponse
    {
        $terms = trim($terms);

        if (mb_strlen($terms) < 2) {
            return response()->json([]);
        }

        $products = app(SearchEngine::class)->suggestProducts($terms, 10)->map(fn (Product $product) => [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'cover' => $product->cover(true),
            'link' => route('frontend.product.show.by_slug', ['slug' => $product->slug()]),
            'price' => $product->formatted_min_price(),
        ])->values();

        return response()->json($products);
    }
}
