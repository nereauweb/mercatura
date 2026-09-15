<?php

namespace App\Console\Commands;

use App\Models\ImportData\VariantPrintingSize;
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
        $total = VariantPrintingSize::count();
        $this->line("Total sizes: $total");
        VariantPrintingSize::chunk(500, function ($printing_sizes) use ($total) {
            foreach ($printing_sizes as $printing_size) {
                $this->count++;
                $this->line('Processing: '.$this->count."/$total");
                if ($printing_size->height_mm == 0) {
                    $printing_size->type = 'circle';
                    $printing_size->label = round($printing_size->width_mm / 10, 1).'cm diametro';
                    $printing_size->save();
                } elseif ($printing_size->label == '0 cm2') {
                    $printing_size->label = round($printing_size->width_mm / 10, 1).'cm x '.round($printing_size->height_mm / 10, 1).'cm';
                    $printing_size->save();
                } else {
                    $printing_size->type = 'rectangle';
                    $printing_size->label = round($printing_size->width_mm / 10, 1).'cm x '.round($printing_size->height_mm / 10, 1).'cm';
                    $printing_size->save();
                }

            }
        });
    }
}
