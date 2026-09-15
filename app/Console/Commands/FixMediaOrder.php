<?php

namespace App\Console\Commands;

use App\Models\ImportData\NormalizedProductVariant;
use Illuminate\Console\Command;

class FixMediaOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:FixMediaOrder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reorder images taking into account main image settings';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $normalized_variants = NormalizedProductVariant::all();
        foreach ($normalized_variants as $normalized_variant) {
            $main_image = $normalized_variant->images()->where('main', 1)->first();
            if (! $main_image) {
                $this->warn('Immagine principale non trovata per la variante normalizzata '.$normalized_variant->source_id);
            } else {
                $variant = $normalized_variant->app_variant;
                if ($variant) {
                    $this->line('Riordino media prodotto normalizzato '.$normalized_variant->source_id.' => variante app id '.$variant->id.' sku '.$variant->sku);
                    foreach ($variant->getMedia('image') as $variant_image) {
                        $this->line($variant_image->file_name.' vs '.$main_image->filename);
                        if ($variant_image->file_name == $main_image->filename) {
                            $variant_image->order_column = 1;
                            $variant_image->save();
                            $this->info('Immagine principale assegnata');

                            continue 2;
                        } elseif ($variant_image->order_column == 1) {
                            $variant_image->order_column = 2;
                            $variant_image->save();
                        }
                    }
                    $this->error('Match immagine non trovato');
                } else {
                    $this->warn('Variante app non trovata per la variante normalizzata '.$normalized_variant->source_id);
                }
            }
        }
    }
}
