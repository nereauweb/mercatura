<?php

namespace App\Console\Commands;

use App\Models\ImportData\NormalizedProduct;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DisableWrongProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:DisableWrongProducts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for products to disable for any reason';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // Runs between the product processing and scout:import: no per-save sync to the search engine.
        Product::disableSearchSyncing();
        try {
            $this->process();
        } finally {
            Product::enableSearchSyncing();
        }
    }

    private function process(): void
    {
        $normalized_products_skus = NormalizedProduct::where('last_seen_active', '>', Carbon::now()->subDays(3)->toDateString())->pluck('source_id')->toArray();
        $products = Product::with('variants')
            ->whereIn('source', app(\App\Support\ImportConnectors::class)->allSourceValues())
            ->get();
        $total_products_count = $products->count();
        $processed_products_count = 0;
        foreach ($products as $product) {
            if ($product->active == 1) {
                $processed_products_count++;
                $this->line('Analisi prodotto '.$processed_products_count.'/'.$total_products_count.' '.$product->sku.' | '.$product->source_sku);
                if (! $product->main_variant_id || ! in_array($product->source_sku, $normalized_products_skus)) {
                    $product->active = false;
                    foreach ($product->variants as $variant) {
                        $variant->update(['active' => 0]);
                    }
                    $this->warn('Prodotto disabilitato (non presente nella fonte dati normalizzata): '.$product->sku.' | '.$product->source_sku);
                } else {
                    foreach ($product->active_variants as $variant) {
                        $normalized_variant = $variant->normalized_variant();
                        if (! $normalized_variant) {
                            $variant->update(['active' => 0]);
                            $this->warn($variant->source_sku.' normalized_variant mancante');
                        } elseif ((int) Carbon::now()->diffInDays($normalized_variant->last_seen_active, true) > 3) {
                            $variant->update(['active' => 0]);
                            $this->warn($variant->source_sku.' normalized_variant outdated last_seen_active:'.$normalized_variant->last_seen_active.' '.(int) Carbon::now()->diffInDays($normalized_variant->last_seen_active, true));
                        } elseif ($variant->prices()->count() == 0) {
                            $variant->update(['active' => 0]);
                            $this->error($variant->source_sku.' prezzo mancante');
                        } else {
                            $this->info($variant->source_sku.' OK');
                        }
                    }
                    $active = false;
                    // min price fix
                    if ($product->variants_min_price == 0) {
                        $product_min_price = false;
                        $product_max_price = false;
                        foreach ($product->variants()->where('active', true)->get() as $active_variant) {
                            foreach ($active_variant->prices as $price) {
                                if (! $product_min_price || $price->price < $product_min_price) {
                                    $product_min_price = $price->price;
                                }
                                if (! $product_max_price || $price->price > $product_max_price) {
                                    $product_max_price = $price->price;
                                }
                            }
                        }

                        $product->update([
                            'variants_min_price' => $product_min_price,
                            'variants_max_price' => $product_max_price,
                        ]);

                    }
                    // min price fix end
                    foreach ($product->active_variants as $variant) {
                        if ($variant->prices()->count() > 0 && $variant->active) {
                            $media = $variant->getFirstMedia('image');
                            if ($media && $product->variants_min_price > 0) {
                                $active = true;
                            }
                        }
                    }
                    if (! $active) {
                        $product->active = false;
                        $this->warn('Prodotto disabilitato (nessuna variante attiva valida): '.$product->sku.' | '.$product->source_sku);
                    }
                }
                $product->save();
            }
        }
    }
}
