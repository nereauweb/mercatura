<?php

namespace App\Console\Commands;

use App\Models\ProductVariant;
use Illuminate\Console\Command;
use Spatie\Image\Image;

class ResizeProductImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ResizeProductImages';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resize Product Variant Images';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        ProductVariant::with('media')->get()->each(function ($model) {
            $this->line('Ridimensionamento immagini variante: '.$model->sku.' ');
            $model->media->each(function ($media) {
                // Ottieni dimensioni
                $dimensions = getimagesize($media->getPath());
                $height = $dimensions[1] ?? 0;

                if ($height > 1600) {
                    // Ridimensiona direttamente il file originale
                    Image::load($media->getPath())
                        ->height(1600)
                        ->save();

                    $this->info('Immagine ridimensionata');
                } else {
                    $this->line('.');
                }
            });
        });
    }
}
