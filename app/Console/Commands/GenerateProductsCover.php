<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class GenerateProductsCover extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:GenerateProductsCover';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rewrite products.cover_url from the main variant thumb. Prefer mercatura:regenerate-product-images after changing conversions.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $products = Product::all();
        foreach ($products as $product) {
            $product->cover($thumb_url = true, $regenerate = true, $regenarate_main_variant = true);
        }
    }
}
