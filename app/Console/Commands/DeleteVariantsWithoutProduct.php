<?php

namespace App\Console\Commands;

use App\Models\ProductVariant;
use Illuminate\Console\Command;

class DeleteVariantsWithoutProduct extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:DeleteVariantsWithoutProduct';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete Variants Without Product';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        foreach (ProductVariant::all() as $variant) {
            if (! $variant->product) {
                $variant->delete();
            }
        }
    }
}
