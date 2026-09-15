<?php

namespace App\Http\Controllers;

use App\Models\Customizations\Customization;
use App\Models\Customizations\CustomizationArea;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\CaughtExceptionLogger;
use App\Support\Connectors\CustomizationPipeline;
use App\Support\Customizations\LinePricer;
use App\Support\FrontendDebugLog;
use App\Support\ProductPageData;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelPdf\Facades\Pdf;

class FrontendProductController extends Controller
{
    public function show_by_id(Request $request, $id)
    {
        FrontendDebugLog::prodottoNavigazione('show_by_id', [
            'product_id' => $id,
        ]);
        try {
            $product = Product::with('customizations')->where('id', $id)->firstOrFail();
        } catch (ModelNotFoundException $e) {
            $this->logProductNavFailure('show_by_id:product_not_found', [
                'reason' => 'product_not_found',
                'product_id' => $id,
                'url' => $request->fullUrl(),
            ], $e);
            abort(404);
        }
        $article = $product->main_variant();
        if (! $article) {
            $this->logProductNavFailure('show_by_id:no_main_variant', [
                'reason' => 'no_main_variant',
                'product_id' => $product->id,
                'slug' => $product->slug,
                'main_variant_id' => $product->main_variant_id,
                'url' => $request->fullUrl(),
            ]);
            abort(404);
        }

        return $this->show_variant($article, $product);
    }

    public function show_by_slug(Request $request, $slug)
    {
        FrontendDebugLog::prodottoNavigazione('show_by_slug', [
            'slug' => $slug,
        ]);
        try {
            $product = Product::with('customizations')->where('slug', $slug)->firstOrFail();
        } catch (ModelNotFoundException $e) {
            $this->logProductNavFailure('show_by_slug:product_not_found', [
                'reason' => 'product_not_found',
                'slug' => $slug,
                'url' => $request->fullUrl(),
            ], $e);
            abort(404);
        }
        $article = $product->main_variant();
        if (! $article) {
            $this->logProductNavFailure('show_by_slug:no_main_variant', [
                'reason' => 'no_main_variant',
                'product_id' => $product->id,
                'slug' => $product->slug,
                'main_variant_id' => $product->main_variant_id,
                'url' => $request->fullUrl(),
            ]);
            abort(404);
        }

        return $this->show_variant($article, $product);
    }

    public function show_variant_by_slug(Request $request, $slug, $sku)
    {
        FrontendDebugLog::prodottoNavigazione('show_variant_by_slug', [
            'slug' => $slug,
            'sku' => $sku,
        ]);
        $article = ProductVariant::where('sku', $sku)->first();
        if (! $article) {
            $this->logProductNavFailure('show_variant_by_slug:variant_not_found', [
                'reason' => 'variant_not_found',
                'slug' => $slug,
                'sku' => $sku,
                'url' => $request->fullUrl(),
            ]);
            abort(404);
        }
        $product = $article->product()->with('customizations')->first();
        if (! $product) {
            $this->logProductNavFailure('show_variant_by_slug:orphan_variant', [
                'reason' => 'orphan_variant',
                'variant_id' => $article->id,
                'slug' => $slug,
                'sku' => $sku,
                'url' => $request->fullUrl(),
            ]);
            abort(404);
        }

        return $this->show_variant($article, $product);
    }

    protected function show_variant($article, $product)
    {
        if (! $article || $article->active == 0 || $product->active == 0) {
            $reason = ! $article
                ? 'missing_article'
                : ($article->active == 0 ? 'variant_inactive' : 'product_inactive');
            $this->logProductNavFailure('show_variant:'.$reason, [
                'reason' => $reason,
                'variant_id' => $article?->id,
                'product_id' => $product->id ?? null,
                'url' => request()->fullUrl(),
            ]);
            abort(404);
        }
        $has_printing = false;
        $has_packaging = false;
        if ($article->customizations()->count() > 0) {
            $has_printing = true;
            if ($article->customizations()->where('has_packaging', 1)->count() > 0) {
                $has_packaging = true;
            }
        }
        $productCategory = $product->categories()->whereNotNull('parent_id')->first();
        $stock = $product->all__variants_stock();
        $productStructuredData = [
            '@context' => 'https://schema.org/',
            '@type' => 'Product',
            'name' => $product->name,
            'image' => secure_url($product->cover(true)),
            'offers' => [
                '@type' => 'Offer',
                'priceCurrency' => 'EUR',
                'price' => (float) $product->variants_min_price,
                'availability' => $stock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'url' => url()->current(),
            ],
            'description' => $product->description,
            'sku' => $product->sku,
            'category' => $productCategory ? $productCategory->name : '',
        ];

        $page = ProductPageData::for($product, $article, $has_printing, $has_packaging);

        return view('frontend.pages.product', compact('product', 'article', 'has_printing', 'has_packaging', 'productStructuredData', 'page'));
    }

    public function get_bestsellers(Request $request)
    {
        $product = Product::find($request->product_id);
        $category = $product->categories()->whereNotNull('parent_id')->first();
        if ($category) {
            $product_ids = Product::select('id')->where('active', 1)->where('isBestseller', 1)->whereHas('categories', function ($q) use ($category) {
                $q->where('categories.id', $category->id);
            })->pluck('products.id')->toArray();
            $bestsellers = Product::whereIn('id', $product_ids)->with(['color_variants.color', 'main_variant_relationship'])->inRandomOrder()->limit(16)->get();
        } else {
            $bestsellers = Product::inRandomOrder()->where('active', 1)->where('isBestseller', 1)->with(['color_variants.color', 'main_variant_relationship'])->limit(16)->get();
        }

        return view('frontend.elements.product-bestsellers', compact('bestsellers'));
    }

    public function get_configurator(Request $request)
    {
        FrontendDebugLog::prodottoNavigazione('get_configurator', [
            'article_id' => $request->article_id,
        ]);
        $article = ProductVariant::with('product')->with('customizations')->where('id', $request->article_id)->first();
        $has_printing = false;
        $has_packaging = false;
        if ($article->customizations()->count() > 0) {
            $has_printing = true;
            if ($article->customizations()->where('has_packaging', 1)->count() > 0) {
                $has_packaging = true;
            }
        }

        $configurator = ProductPageData::for($article->product, $article, $has_printing, $has_packaging)['configurator'];

        return view('frontend.components.product.configurator', ['article' => $article, 'configurator' => $configurator, 'product' => $article->product]);
    }

    public function print_summary(Request $request)
    {
        FrontendDebugLog::prodottoNavigazione('print_summary', [
            'product_id' => $request->product_id,
        ]);
        $product = Product::find($request->product_id);
        $request_data = json_decode($request->summary_data, true);
        $request->merge(['articles' => $request_data['articles']]);
        $request->merge(['printings' => $request_data['printings']]);
        $request->merge(['has_packaging' => $request_data['has_packaging']]);
        $summary_data = $this->build_articles_request($request, true);

        return Pdf::view('frontend.pdf.configurator_summary', ['data' => $summary_data])
            ->format('a4')
            ->name(\Illuminate\Support\Str::slug((string) config('brand.name')).'-'.$product->sku.'-'.date('Y-m-d').'.pdf');
    }

    public function build_articles_request(Request $request, $return_data = false)
    {
        FrontendDebugLog::prezzoConfiguratore('******** INIZIO NUOVA RICHIESTA (CONFIGURATORE)');
        $line = app(LinePricer::class)->price(
            array_map(fn ($article): array => [intval($article[0]), intval($article[1])], (array) $request->articles),
            array_map('intval', (array) ($request->printings ?: [])),
            $request->has_packaging == '1',
        );

        $lines = [];
        foreach ($line->articles as $article) {
            $variant = $article->variant;
            $lines[] = [
                'column_1_style' => '',
                'column_1' => $variant->sku.' <span style="display:inline-block;width: 12px;min-width: 12px;height: 12px;min-height: 12px;border-radius: 12px;border: 1px solid #cccccc;'.$variant->color->render_code().'"></span>&nbsp;'.($variant->size ? $variant->size->shown_label() : 'Unica'),
                'column_2' => $article->quantity.'x'.number_format($article->unitPrice, 2).'&nbsp;&euro;',
                'column_3' => number_format($article->price, 2, ',', '.').'&nbsp;&euro;',
            ];
            foreach ($article->customizations as $customization) {
                $lines[] = [
                    'column_1_style' => 'padding-left:20px;',
                    'column_1' => $customization->label,
                    'column_2' => $customization->quantity.'x'.number_format($customization->unitPrice, 2, ',', '.').'&nbsp;&euro;',
                    'column_3' => number_format($customization->price, 2, ',', '.').'&nbsp;&euro;',
                ];
                if ($line->packaging) {
                    $lines[] = [
                        'column_1_style' => 'padding-left:20px;',
                        'column_1' => __('frontend.customization.packaging'),
                        'column_2' => $customization->quantity.'x'.number_format((float) $customization->packagingUnitPrice, 2, ',', '.').'&nbsp;&euro;',
                        'column_3' => number_format($customization->packagingPrice, 2, ',', '.').'&nbsp;&euro;',
                    ];
                }
            }
        }
        if ($line->underMinimum()) {
            $lines[] = [
                'column_1_style' => '',
                'column_1' => __('frontend.customization.under_minimum', ['minimum' => $line->minimumQuantity]),
                'column_2' => '',
                'column_3' => number_format($line->surcharge, 2, ',', '.').'&nbsp;&euro;',
            ];
        }
        foreach ($line->customizations as $customization) {
            if ($customization->startCost > 0) {
                $lines[] = [
                    'column_1_style' => '',
                    'column_1' => $customization->option->startLabel(),
                    'column_2' => '',
                    'column_3' => number_format($customization->startCost, 2, ',', '.').'&nbsp;&euro;',
                ];
            }
            $lines[] = [
                'column_1_style' => '',
                'column_1' => $customization->option->setupLabel(),
                'column_2' => number_format($customization->setupMultiplier, 0).'x'.number_format($customization->setup, 2, ',', '.'),
                'column_3' => number_format($customization->setupPrice, 2, ',', '.').'&nbsp;&euro;',
            ];
        }

        FrontendDebugLog::prezzoConfiguratore('Totali: prezzo '.$line->price.' | IVA '.$line->vat().' | costi aggiuntivi '.$line->additionalCosts.' | quantità '.$line->quantity);
        $response = [
            'lines' => $lines,
            'total_price' => number_format($line->price, 2, ',', '.').'&nbsp;&euro;',
            'unit_price' => number_format($line->unitPriceWithAdditionalCosts(), 2, ',', '.').'&nbsp;&euro;',
            'total_vat' => number_format($line->vat(), 2, ',', '.').'&nbsp;&euro;',
            'total_quantity' => number_format($line->quantity, 0),
            'total_additional_costs_amount' => round($line->additionalCosts, 2),
            'total_additional_costs' => number_format($line->additionalCosts, 2).'&nbsp;&euro;',
            'total_taxed_price' => number_format($line->totalTaxedPrice(), 2, ',', '.').'&nbsp;&euro;',
            'unit_taxed_price' => number_format($line->totalTaxedPrice() / $line->quantity, 2, ',', '.').'&nbsp;&euro;',
        ];

        return $return_data ? $response : response()->json($response);
    }

    public function get_product_variants_stock(Request $request)
    {
        if (! $request->product_id) {
            return 0;
        } // add validation

        return ProductVariant::where('product_id', $request->product_id)
            ->when($request->color_id && $request->color_id != 0, function ($q) use ($request) {
                return $q->where('color_id', $request->color_id);
            })
            ->when($request->size_id && $request->size_id != 0, function ($q) use ($request) {
                return $q->where('size_id', $request->size_id);
            })
            ->sum('stock');
    }

    public function get_printing_image_and_sizes(Request $request)
    {
        $printing = Customization::find($request->printing_id);
        if (! CustomizationPipeline::isLive($printing)) {
            return response()->json(['sizes' => [], 'image' => null], 404);
        }

        return response()->json([
            'sizes' => $printing->areas->toArray(),
            'image' => $printing->image,
        ]);
    }

    public function get_printing_colors_by_size(Request $request)
    {
        $area = CustomizationArea::find($request->printing_size_id);
        if (! $area || ! CustomizationPipeline::isLive($area->customization)) {
            return response()->json([], 404);
        }

        return response()->json($area->options->toArray());
    }

    protected function logProductNavFailure(string $event, array $context, ?\Throwable $e = null): void
    {
        if ($e !== null) {
            CaughtExceptionLogger::error('Frontend product navigation: '.($context['reason'] ?? $event), $e, $context);
        } else {
            Log::warning(
                'Frontend product navigation: '.($context['reason'] ?? $event),
                $context
            );
        }
        FrontendDebugLog::prodottoNavigazione($event, $context);
    }
}
