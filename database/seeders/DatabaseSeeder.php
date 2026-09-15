<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * `migrate --seed` on a fresh clone gives a browsable, neutral demo shop
 * (docs/ARCHITECTURE.md §9 Phase 6). Installations seed their own data with
 * `db:seed --class=CoreSeeder` and their own seeders instead.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CoreSeeder::class);
        $this->call(DemoSeeder::class);
    }
}
