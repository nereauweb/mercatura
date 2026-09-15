<?php

namespace App\Http\Controllers;

use App\Models\ImportData\VariantPrinting;
use App\Models\ImportData\VariantPrintingColor;
use App\Models\ImportData\VariantPrintingSize;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\CaughtExceptionLogger;
use App\Support\Connectors\PrintingPipeline;
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
            $product = Product::with('printings')->where('id', $id)->firstOrFail();
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
            $product = Product::with('printings')->where('slug', $slug)->firstOrFail();
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
        $product = $article->product()->with('printings')->first();
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
        if ($article->printings()->count() > 0) {
            $has_printing = true;
            if ($article->printings()->where('has_packaging', 1)->count() > 0) {
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
        $article = ProductVariant::with('product')->with('printings')->where('id', $request->article_id)->first();
        $has_printing = false;
        $has_packaging = false;
        if ($article->printings()->count() > 0) {
            $has_printing = true;
            if ($article->printings()->where('has_packaging', 1)->count() > 0) {
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
        $lines = [];
        // $print_lines = [];
        $total_quantity = 0;
        $total_price = 0;
        $total_additional_costs = 0;
        $with_packaging = $request->has_packaging == '1' ? true : false;
        $minimum = 0;
        $under_minimum = false;
        foreach ($request->articles as $article_request) {
            $article_request_id = intval($article_request[0]);
            $article_request_quantity = intval($article_request[1]);
            $total_quantity += $article_request_quantity;
        }
        FrontendDebugLog::prezzoConfiguratore("Quantità totale richiesta: $total_quantity");
        FrontendDebugLog::prezzoConfiguratore('--- Calcolo prezzi articoli ---');
        foreach ($request->articles as $article_request) {
            $article_request_id = intval($article_request[0]);
            $article_request_quantity = intval($article_request[1]);
            $article = ProductVariant::find($article_request_id);
            FrontendDebugLog::prezzoConfiguratore("Articolo: $article->sku");
            $article_original_price = $article->price_per_quantity($total_quantity, true);
            FrontendDebugLog::prezzoConfiguratore("Prezzo originario (determinato da quantità totale della richiesta): $article_original_price");
            $article_markup_percent = $article->get_markup_percent($total_quantity, $article_original_price);
            FrontendDebugLog::prezzoConfiguratore("Markup articolo (determinato da quantità totale della richiesta x prezzo originario su tabella markup): $article_markup_percent %");
            $article_markup = round($article_original_price * ($article_markup_percent / 100), 2);
            $article_price = $article_original_price + $article_markup;
            $quantity_price = $article_request_quantity * $article_price;
            $additional_costs = $article_request_quantity * $article->additional_unit_costs_per_quantity($article_request_quantity);
            FrontendDebugLog::prezzoConfiguratore("Prezzo singolo (prezzo originario + markup): $article_price | Quantità articolo richiesta: $article_request_quantity | Prezzo quantità (prezzo singolo x quantità articolo richiesta): $quantity_price | Costi aggiuntivi: $additional_costs");
            array_push($lines, [
                'column_1_style' => '',
                'column_1' => $article->sku.' <span style="display:inline-block;width: 12px;min-width: 12px;height: 12px;min-height: 12px;border-radius: 12px;border: 1px solid #cccccc;'.$article->color->render_code().'"></span>&nbsp;'.($article->size ? $article->size->shown_label() : 'Unica'),
                // 'column_2' => $article_request_quantity . 'x' . number_format($article_price,2) .'&nbsp;&euro; ('.number_format($article_original_price,2).')',
                'column_2' => $article_request_quantity.'x'.number_format($article_price, 2).'&nbsp;&euro;',
                'column_3' => number_format($quantity_price, 2, ',', '.').'&nbsp;&euro;',
            ]);
            $total_additional_costs += $additional_costs;
            $total_price += $quantity_price;
            if ($request->printings) {
                FrontendDebugLog::prezzoConfiguratore('--- Calcolo personalizzazioni articolo ---');
                foreach ($request->printings as $printing_color_id) {
                    $main_printing_color = VariantPrintingColor::find($printing_color_id);
                    if (! PrintingPipeline::colorIsLive($main_printing_color)) {
                        continue;
                    }
                    // article printing color
                    $printing_color = $main_printing_color->sibling($article_request_id);
                    // original printing prices
                    $original_printing_price = $printing_color->calculate_print_price($total_quantity, $article_request_quantity, $with_packaging, true);
                    // printing price
                    FrontendDebugLog::prezzoConfiguratore("[Personalizzazione richiesta] VariantPrintingColor $printing_color->id | Quantità totale: $total_quantity | Quantità articolo: $article_request_quantity | Packaging: ".($with_packaging ? 'Sì' : 'No')." | Markup (da markup articolo): $article_markup_percent %");
                    $printing_price = $printing_color->calculate_print_price($total_quantity, $article_request_quantity, $with_packaging, false, $article_markup_percent);
                    $total_price += $printing_price['price'];
                    $printing_label = $printing_color->printing_label();
                    array_push($lines, [
                        'column_1_style' => 'padding-left:20px;',
                        'column_1' => $printing_label,
                        // 'column_2' => $printing_price['quantity'] . 'x' . number_format($printing_price['unit_price'],2,',','.') .'&nbsp;&euro; ('.number_format($original_printing_price['unit_price'],2,',','.').')',
                        'column_2' => $printing_price['quantity'].'x'.number_format($printing_price['unit_price'], 2, ',', '.').'&nbsp;&euro;',
                        'column_3' => number_format($printing_price['price'], 2, ',', '.').'&nbsp;&euro;',
                    ]);
                    FrontendDebugLog::prezzoConfiguratore("[Personalizzazione senza markup] $printing_label => ".$original_printing_price['quantity'].' x '.$original_printing_price['unit_price'].' = '.$original_printing_price['price']);
                    FrontendDebugLog::prezzoConfiguratore("[Personalizzazione con markup] $printing_label => ".$printing_price['quantity'].' x '.$printing_price['unit_price'].' = '.$printing_price['price']);
                    // packaging
                    if (isset($printing_price['packaging_price'])) {
                        array_push($lines, [
                            'column_1_style' => 'padding-left:20px;',
                            'column_1' => 'Confezionamento',
                            // 'column_2' => $printing_price['packaging_quantity'] . 'x' . number_format($printing_price['packaging_unit_price'],2,',','.') .'&nbsp;&euro; ('.number_format($original_printing_price['packaging_unit_price'],2,',','.').')',
                            'column_2' => $printing_price['packaging_quantity'].'x'.number_format($printing_price['packaging_unit_price'], 2, ',', '.').'&nbsp;&euro;',
                            'column_3' => number_format($printing_price['packaging_price'], 2, ',', '.').'&nbsp;&euro;',
                        ]);
                        FrontendDebugLog::prezzoConfiguratore("[Personalizzazione packaging] $printing_label => ".$printing_price['packaging_quantity'].' x '.$printing_price['packaging_unit_price'].' = '.$printing_price['packaging_price']);
                        $total_price += $printing_price['packaging_price'];
                    }
                    // set minimum
                    $this_minimum = $printing_color->printing_size->printing->minimum_quantity;
                    if ($minimum == 0) {
                        $minimum = $this_minimum ?? 0;
                    } else {
                        if ($minimum < $this_minimum) {
                            $minimum = $this_minimum ?? 0;
                        }
                    }
                }
            }
        }
        // check minimum
        if ($total_quantity < $minimum) {
            $original_under_minimum = 35;
            $under_minimum = 40;
            $total_price += $under_minimum;
            array_push($lines, [
                'column_1_style' => '',
                'column_1' => 'Sotto soglia minima ('.$minimum.' pz)',
                // 'column_2' => '('.number_format($original_under_minimum,2,',','.').')',
                'column_2' => '',
                'column_3' => number_format($under_minimum, 2, ',', '.').'&nbsp;&euro;',
            ]);
            FrontendDebugLog::prezzoConfiguratore("--- Quantità totale sotto soglia minima ($minimum pz), applicato sovrapprezzo fisso al totale: + $under_minimum");
        }
        if ($request->printings) {
            FrontendDebugLog::prezzoConfiguratore('--- Calcolo avviamento e impianto personalizzazioni ---');
            foreach ($request->printings as $printing_color_id) {
                $printing_color = VariantPrintingColor::find($printing_color_id);
                if (! PrintingPipeline::colorIsLive($printing_color)) {
                    continue;
                }
                $printing_label = $printing_color->printing_label();
                FrontendDebugLog::prezzoConfiguratore("Personalizzazione: $printing_label (ID VariantPrintingColor $printing_color->id)");
                // start_cost
                if ($printing_color->start_cost > 0) {
                    array_push($lines, [
                        'column_1_style' => '',
                        'column_1' => 'Avviamento',
                        // 'column_2' => '('.number_format($printing_color->original_start_cost,2,',','.').')',
                        'column_2' => '',
                        'column_3' => number_format($printing_color->start_cost, 2, ',', '.').'&nbsp;&euro;',
                    ]);
                    $total_price += $printing_color->start_cost;
                    FrontendDebugLog::prezzoConfiguratore('[Avviamento] '.$printing_color->start_cost.' (originale: '.$printing_color->original_start_cost.')');
                }
                // setup
                $setup_multiplier = $printing_color->setup_multiplier == 0 ? 1 : $printing_color->setup_multiplier;
                $setup_price = $printing_color->setup * $setup_multiplier;
                array_push($lines, [
                    'column_1_style' => '',
                    'column_1' => $printing_color->setup_label(),
                    // 'column_2' => number_format($printing_color->setup_multiplier,0) . 'x' . number_format($printing_color->setup,2,',','.') .' ('.number_format($printing_color->original_setup,2,',','.').')',
                    'column_2' => number_format($setup_multiplier, 0).'x'.number_format($printing_color->setup, 2, ',', '.'),
                    'column_3' => number_format($setup_price, 2, ',', '.').'&nbsp;&euro;',
                ]);
                $total_price += $setup_price;
                FrontendDebugLog::prezzoConfiguratore("[Impianto] Prezzo: $printing_color->setup (originale: $printing_color->original_setup) | Moltiplicatore: $setup_multiplier | Prezzo finale impianto: $setup_price");
            }
        }
        FrontendDebugLog::prezzoConfiguratore('--- Calcolo totali ---');
        $total_vat = round($total_price * 0.22, 2);
        FrontendDebugLog::prezzoConfiguratore("Prezzo totale (senza IVA): $total_price | IVA: $total_vat | Costi aggiuntivi (no IVA): $total_additional_costs");
        $response = [];
        $response['lines'] = $lines;
        $response['total_price'] = number_format($total_price, 2, ',', '.').'&nbsp;&euro;';
        $unit_price = ($total_price + $total_additional_costs) / $total_quantity;
        $response['unit_price'] = number_format($unit_price, 2, ',', '.').'&nbsp;&euro;';
        FrontendDebugLog::prezzoConfiguratore("Prezzo unitario (prezzo totale + costi aggiuntivi / quantità totale): $unit_price");
        $response['total_vat'] = number_format($total_vat, 2, ',', '.').'&nbsp;&euro;';
        $response['total_quantity'] = number_format($total_quantity, 0);
        $response['total_additional_costs_amount'] = round($total_additional_costs, 2);
        $response['total_additional_costs'] = number_format($total_additional_costs, 2).'&nbsp;&euro;';
        $total_taxed_price = $total_price + $total_vat + $total_additional_costs;
        $response['total_taxed_price'] = number_format($total_taxed_price, 2, ',', '.').'&nbsp;&euro;';
        FrontendDebugLog::prezzoConfiguratore("Prezzo totale incluso IVA (prezzo totale + IVA + costi aggiuntivi): $total_taxed_price");
        $unit_taxed_price = ($total_price + $total_vat + $total_additional_costs) / $total_quantity;
        $response['unit_taxed_price'] = number_format($unit_taxed_price, 2, ',', '.').'&nbsp;&euro;';
        FrontendDebugLog::prezzoConfiguratore("Prezzo unitario incluso IVA (prezzo totale incluso IVA / quantità totale): $unit_taxed_price");
        // return data or response
        if ($return_data) {
            return $response;
        }

        return response()->json($response);
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
        $printing = VariantPrinting::find($request->printing_id);
        if (! PrintingPipeline::printingIsLive($printing)) {
            return response()->json(['sizes' => [], 'image' => null], 404);
        }

        return response()->json([
            'sizes' => $printing->printing_sizes->toArray(),
            'image' => $printing->image,
        ]);
    }

    public function get_printing_sizes_by_position(Request $request)
    {
        $printing = VariantPrinting::find($request->printing_id);
        if (! PrintingPipeline::printingIsLive($printing)) {
            return response()->json([], 404);
        }

        return response()->json($printing->printing_sizes->toArray());
    }

    public function get_printing_colors_by_size(Request $request)
    {
        $printing_size = VariantPrintingSize::find($request->printing_size_id);
        if (! $printing_size || ! PrintingPipeline::printingIsLive($printing_size->printing)) {
            return response()->json([], 404);
        }

        return response()->json($printing_size->printing_colors->toArray());
    }

    public function get_printing_price_for_selection(Request $request)
    {
        $printing_color = VariantPrintingColor::find($request->printing_color_id);
        if (! PrintingPipeline::colorIsLive($printing_color)) {
            return response()->json([], 404);
        }

        return response()->json($printing_color->calculate_print_price($request->quantity));
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
