<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\ImportData\NormalizedProduct;
use App\Models\ImportData\NormalizedProductVariant;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeleteProductsByCategory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:delete-by-category
        {category : ID della categoria}
        {--dry-run : Mostra cosa verrebbe eliminato senza cancellare nulla}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Elimina tutti i prodotti di una categoria (e sottocategorie) da products e normalized_products, inclusi media e record collegati';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $categoryId = (int) $this->argument('category');
        $dryRun = (bool) $this->option('dry-run');

        $category = Category::find($categoryId);
        if (! $category) {
            $this->error("Categoria con ID {$categoryId} non trovata.");

            return self::FAILURE;
        }

        // 1. Categoria target + tutte le discendenti (ricorsivo).
        $categoryIds = $this->collectCategoryIds($categoryId);

        $this->info("Categoria: [{$category->id}] {$category->name}");
        $this->line('Categorie coinvolte (target + discendenti): '.implode(', ', $categoryIds));

        // 2. Prodotti distinti collegati a queste categorie via pivot.
        $productIds = DB::table('category_product')
            ->whereIn('category_id', $categoryIds)
            ->pluck('product_id')
            ->unique()
            ->values();

        $products = Product::whereIn('id', $productIds)->get();

        if ($products->isEmpty()) {
            $this->warn('Nessun prodotto collegato a queste categorie.');

            return self::SUCCESS;
        }

        // 3. source_sku per agganciare i normalized_products.
        $sourceSkus = $products->pluck('source_sku')->filter()->unique()->values();

        $this->info("Prodotti da eliminare: {$products->count()}");

        $normalizedCount = $sourceSkus->isNotEmpty()
            ? NormalizedProduct::whereIn('source_id', $sourceSkus)->count()
            : 0;
        $this->info("NormalizedProducts collegati da eliminare: {$normalizedCount}");

        // Avviso storico ordini (order_items non ha FK).
        $orderItemsCount = DB::table('order_items')->whereIn('product_id', $productIds)->count();
        if ($orderItemsCount > 0) {
            $this->warn("Attenzione: {$orderItemsCount} righe in order_items referenziano questi prodotti (storico ordini).");
        }

        if ($dryRun) {
            $this->newLine();
            $this->line('--- DRY RUN: nessuna cancellazione eseguita ---');
            $this->table(
                ['id', 'sku', 'source_sku', 'name'],
                $products->map(fn ($p) => [$p->id, $p->sku, $p->source_sku, $p->name])->all()
            );

            return self::SUCCESS;
        }

        if (! $this->confirm("Confermi l'eliminazione DEFINITIVA di {$products->count()} prodotti e {$normalizedCount} normalized_products?")) {
            $this->line('Operazione annullata.');

            return self::SUCCESS;
        }

        $this->deleteProducts($products);
        $this->deleteNormalizedProducts($sourceSkus);

        $this->newLine();
        $this->info('Eliminazione completata.');

        return self::SUCCESS;
    }

    /**
     * Raccoglie l'ID categoria e tutti i suoi discendenti ricorsivamente.
     *
     * @return array<int>
     */
    private function collectCategoryIds(int $rootId): array
    {
        $ids = [$rootId];
        $queue = [$rootId];

        while ($queue) {
            $parentId = array_shift($queue);
            $childrenIds = Category::where('parent_id', $parentId)->pluck('id')->all();
            foreach ($childrenIds as $childId) {
                if (! in_array($childId, $ids, true)) {
                    $ids[] = $childId;
                    $queue[] = $childId;
                }
            }
        }

        return $ids;
    }

    /**
     * Elimina i prodotti app e tutti i record figli (media inclusi).
     */
    private function deleteProducts($products): void
    {
        DB::transaction(function () use ($products) {
            $bar = $this->output->createProgressBar($products->count());
            $bar->start();

            foreach ($products as $product) {
                $variantIds = $product->variants()->pluck('id')->all();

                // customizations (per prodotto e per variante).
                // La catena customization_areas/_colors/_prices ha cascade interno.
                DB::table('customizations')
                    ->where('product_id', $product->id)
                    ->orWhereIn('variant_id', $variantIds ?: [0])
                    ->delete();

                if ($variantIds) {
                    DB::table('products_variants_prices')->whereIn('variant_id', $variantIds)->delete();
                    DB::table('products_variants_future_stocks')->whereIn('variant_id', $variantIds)->delete();
                    DB::table('products_variants_attributes')->whereIn('variant_id', $variantIds)->delete();
                }
                DB::table('products_variants_attributes')->where('product_id', $product->id)->delete();

                // Pulisce il pivot category_product.
                $product->categories()->detach();

                // Elimina le varianti una a una: Spatie rimuove i record media e i file su disco.
                ProductVariant::whereIn('id', $variantIds)->get()->each->delete();

                // Hard delete del prodotto (+ rimozione da Algolia via Scout).
                $product->delete();

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        });
    }

    /**
     * Elimina i normalized_products collegati e tutti i record figli.
     */
    private function deleteNormalizedProducts($sourceSkus): void
    {
        if ($sourceSkus->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($sourceSkus) {
            $normalized = NormalizedProduct::whereIn('source_id', $sourceSkus)->get();

            if ($normalized->isEmpty()) {
                return;
            }

            $bar = $this->output->createProgressBar($normalized->count());
            $bar->start();

            foreach ($normalized as $np) {
                $variantIds = $np->variants()->pluck('id')->all();

                if ($variantIds) {
                    DB::table('normalized_products_variants_colors')->whereIn('variant_id', $variantIds)->delete();
                    DB::table('normalized_products_variants_future_stocks')->whereIn('variant_id', $variantIds)->delete();
                    DB::table('normalized_products_variants_images')->whereIn('variant_id', $variantIds)->delete();
                    DB::table('normalized_products_variants_prices')->whereIn('variant_id', $variantIds)->delete();
                }

                DB::table('customizations')
                    ->where('normalized_product_id', $np->id)
                    ->orWhereIn('normalized_variant_id', $variantIds ?: [0])
                    ->delete();

                NormalizedProductVariant::whereIn('id', $variantIds)->delete();

                $np->delete();

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        });
    }
}
