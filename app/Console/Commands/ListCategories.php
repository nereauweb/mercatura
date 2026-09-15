<?php

namespace App\Console\Commands;

use App\Models\Category;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ListCategories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'categories:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Elenca tutte le categorie con ID, struttura ad albero e numero di prodotti collegati';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Conteggio prodotti per categoria (via pivot category_product) in una sola query.
        $productCounts = DB::table('category_product')
            ->select('category_id', DB::raw('COUNT(DISTINCT product_id) as total'))
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $categories = Category::orderBy('parent_id')->orderBy('name')->get();
        $childrenByParent = $categories->groupBy('parent_id');

        $total = $categories->count();
        $this->info("Categorie totali: {$total}");
        $this->newLine();

        // Root: parent_id NULL (Eloquent raggruppa le chiavi null sotto la stringa vuota '').
        $roots = $childrenByParent->get(null) ?? $childrenByParent->get('');

        if (! $roots) {
            $this->warn('Nessuna categoria root trovata.');

            return self::SUCCESS;
        }

        foreach ($roots as $root) {
            $this->printNode($root, $childrenByParent, $productCounts, 0);
        }

        return self::SUCCESS;
    }

    /**
     * Stampa ricorsivamente un nodo categoria e i suoi figli.
     */
    private function printNode(Category $category, $childrenByParent, $productCounts, int $depth): void
    {
        $indent = str_repeat('    ', $depth);
        $count = (int) ($productCounts[$category->id] ?? 0);

        $this->line(sprintf(
            '%s[%d] %s (%d prodotti)',
            $indent,
            $category->id,
            $category->name,
            $count
        ));

        $children = $childrenByParent->get($category->id);
        if ($children) {
            foreach ($children as $child) {
                $this->printNode($child, $childrenByParent, $productCounts, $depth + 1);
            }
        }
    }
}
