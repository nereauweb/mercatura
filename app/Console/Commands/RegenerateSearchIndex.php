<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RegenerateSearchIndex extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:RegenerateSearchIndex';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate Search Index';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // Same as scout:import for products, without depending on Scout's console-only commands
        // (they are not registered when this runs from a queued job or a web request).
        \App\Models\Product::makeAllSearchable();
        $this->info('Products sent to the search index.');
    }
}
