<?php

namespace App\Console\Commands;

use App\Models\ImportData\NormalizedProduct;
use Illuminate\Console\Command;

class UpdateProductsDescriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:UpdateProductsDescriptions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update products descriptions';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $normalized_products = NormalizedProduct::all();
        foreach ($normalized_products as $normalized_product) {
            $product = $normalized_product->app_product;
            if ($product) {
                $this->line('Aggiornamento descrizione prodotto normalizzato '.$normalized_product->source_id.' => prodotto app id '.$product->id.' sku '.$product->sku);
                $normalized_variant = $normalized_product->main_variant();
                if ($normalized_variant) {
                    $description = $normalized_variant->full_description ?? $normalized_variant->short_description;
                    if ($description && $description != $product->description) {
                        $product->update(['description' => $description]);
                        $this->info('Descrizione aggiornata');
                    } else {
                        $this->warn('Descrizione coincidente, skipped');
                    }
                } else {
                    $this->error('Variante principale non trovata per il prodotto normalizzato '.$normalized_product->source_id);
                }
            } else {
                $this->error('Prodotto app non trovato per il prodotto normalizzato '.$normalized_product->source_id);
            }
        }
    }
}
