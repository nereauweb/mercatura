<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use Database\Seeders\Demo\DemoCatalogSeeder;
use Database\Seeders\Demo\DemoContentSeeder;
use Database\Seeders\Demo\DemoCustomersSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * Neutral demo shop: a browsable catalogue with pictures and printing
 * options, CMS pages, home slides, a blog, customers with orders and
 * quotations, and an admin. Runs once: it refuses to seed on top of an
 * existing demo. Needs no external service (pictures are generated with
 * GD, search uses the collection driver).
 *
 *   php artisan migrate --seed          # CoreSeeder + DemoSeeder
 *   php artisan db:seed --class=DemoSeeder
 *
 * Logins (password "password"): admin@example.com, cliente.azienda@example.com,
 * cliente.privato@example.com, cliente.pa@example.com.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Product::query()->where('sku', 'like', DemoCatalogSeeder::SKU_PREFIX.'%')->exists()) {
            $this->command->warn('Demo data already present (products '.DemoCatalogSeeder::SKU_PREFIX.'*): nothing to do. Use migrate:fresh --seed to rebuild.');

            return;
        }

        $this->call(CoreSeeder::class);

        Product::withoutSyncingToSearch(function (): void {
            $this->call(DemoCatalogSeeder::class);
            $this->call(DemoContentSeeder::class);
            $this->call(DemoCustomersSeeder::class);
        });

        // Navigation, filters and listings are cached: start clean.
        Cache::flush();
        $this->command->info('Demo shop seeded. Admin: '.DemoCustomersSeeder::ADMIN_EMAIL.' / '.DemoCustomersSeeder::PASSWORD.'. Run scout:import "App\\Models\\Product" if the search provider needs an index.');
    }
}
