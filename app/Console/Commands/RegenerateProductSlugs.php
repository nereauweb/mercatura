<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class RegenerateProductSlugs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:RegenerateProductSlugs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate all products slugs';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $products = Product::all();
        foreach ($products as $product) {
            $product->slug(true);
        }
    }
}
