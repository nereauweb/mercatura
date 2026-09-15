<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class DeleteProductsWithoutVariants extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:DeleteProductsWithoutVariants';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete Products Without Variants';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        foreach (Product::all() as $product) {
            if ($product->variants()->count() == 0) {
                $product->delete();
            }
        }
    }
}
