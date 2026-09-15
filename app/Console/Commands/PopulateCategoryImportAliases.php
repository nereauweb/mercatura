<?php

namespace App\Console\Commands;

use App\Models\CategoryImportAlias;
use App\Models\ImportData\NormalizedProduct;
use Illuminate\Console\Command;

class PopulateCategoryImportAliases extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'normalize:UpdateCategoryAliases';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update category aliases';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $normalized_products = NormalizedProduct::all();
        $total_products = $normalized_products->count();
        $processed_products = 0;

        foreach ($normalized_products as $normalized_product) {
            if (! $normalized_product->category) {
                continue;
            }
            CategoryImportAlias::UpdateOrCreate(
                [
                    'source' => $normalized_product->source,
                    'parent_category_ref' => $normalized_product->parent_category,
                    'category_ref' => $normalized_product->category,
                ]
            );
        }
    }
}
