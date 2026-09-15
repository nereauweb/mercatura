<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class FixMainVariantIds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:FixMainVariantIds';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix Main Variant Ids';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $products = Product::with('variants')->get();
        $this->info('Found '.$products->count().' products to process');

        $processed = 0;
        $updated = 0;

        foreach ($products as $product) {
            $processed++;
            $old_main_variant_id = $product->main_variant_id;

            // Use set_main_variant with false to choose randomly from valid variants
            // Valid variants must be: active, have media, and have prices
            $result = $product->set_main_variant(false, true);

            if ($result && $result->id != $old_main_variant_id) {
                $updated++;
                $this->info("Product {$product->id} ({$product->name}): Main variant changed from {$old_main_variant_id} to {$result->id}");
            } elseif ($result) {
                $this->line("Product {$product->id} ({$product->name}): Main variant unchanged ({$result->id})");
            } else {
                $this->warn("Product {$product->id} ({$product->name}): No valid variants found");
            }
        }

        $this->info("Processed {$processed} products, updated {$updated} main variants");
    }
}
