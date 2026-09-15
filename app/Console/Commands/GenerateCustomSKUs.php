<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Console\Command;

class GenerateCustomSKUs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:GenerateCustomSKUs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate all skus';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        foreach (Product::all() as $product) {
            $product_sku = 'G'.$product->id;
            $product->update(['sku' => $product->source_sku]);
            $this->line($product->sku);
        }
        foreach (ProductVariant::all() as $variant) {
            $variant_sku = 'G'.$variant->product_id.'.'.$variant->id;
            $variant->update(['sku' => $variant->source_sku]);
            $this->line($variant->sku);
        }
    }
}
