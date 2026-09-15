<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class CheckProductsWithoutCategory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:CheckProductsWithoutCategory';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for products without assigned category';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $products = Product::all();
        $product_without_categories = 0;
        foreach ($products as $product) {
            if ($product->categories->count() == 0) {
                $this->error('Prodotto '.$product->source_sku.' : nessuna categoria assegnata');
                $product_without_categories++;
            } /*else {
                $this->info('Prodotto ' . $product->source_sku .', categorie : ');
                $categories = [];
                foreach ($product->categories as $category){
                    if (!in_array($category->id,$categories)){
                        array_push($categories,$category->id);
                    }
                }
                $product->categories()->detach();
                $product->categories()->sync($categories);
                foreach ($product->categories as $category){
                    $this->info($category->name);
                }
            }*/
        }
        $this->info('Prodotti senza categoria assegnata: '.$product_without_categories);
    }
}
