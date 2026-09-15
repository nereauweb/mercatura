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
        $this->call('scout:import', ['model' => 'App\Models\Product']);
    }
}
