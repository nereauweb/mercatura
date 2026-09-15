<?php

namespace App\Console\Commands;

use App\Models\Customizations\CustomizationArea;
use Illuminate\Console\Command;

class FixPrintingSizeLabels extends ImportCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:FixPrintingSizeLabels';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix print size labels';

    private $count = 0;

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('Process started');
        $total = CustomizationArea::count();
        $this->line("Total sizes: $total");
        CustomizationArea::chunk(500, function ($areas) use ($total) {
            foreach ($areas as $area) {
                $this->count++;
                $this->line('Processing: '.$this->count."/$total");
                if ($area->height_mm == 0) {
                    $area->type = 'circle';
                    $area->label = round($area->width_mm / 10, 1).'cm diametro';
                    $area->save();
                } elseif ($area->label == '0 cm2') {
                    $area->label = round($area->width_mm / 10, 1).'cm x '.round($area->height_mm / 10, 1).'cm';
                    $area->save();
                } else {
                    $area->type = 'rectangle';
                    $area->label = round($area->width_mm / 10, 1).'cm x '.round($area->height_mm / 10, 1).'cm';
                    $area->save();
                }

            }
        });
    }
}
