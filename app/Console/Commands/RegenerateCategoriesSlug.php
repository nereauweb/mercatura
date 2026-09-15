<?php

namespace App\Console\Commands;

use App\Models\Category;
use Illuminate\Console\Command;

class RegenerateCategoriesSlug extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:RegenerateCategoriesSlug';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate category aliases';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $categories = Category::all();
        foreach ($categories as $category) {
            $category->update([
                'slug' => urlencode(preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', $category->name))),
            ]);
        }
    }
}
