<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ImportProductsJob;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/** An import started from the panel runs the same steps as the nightly app:import. */
final class PanelImportSequenceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_the_products_job_runs_colour_codes_search_index_and_sitemap(): void
    {
        $called = [];
        Artisan::shouldReceive('call')->andReturnUsing(function (string $command) use (&$called): int {
            $called[] = $command;

            return 0;
        });

        (new ImportProductsJob(['download_data' => false, 'process_product_data' => true]))->handle();

        foreach (['app:ProcessNormalizedProductData', 'catalog:color-codes', 'app:DisableWrongProducts', 'app:RegenerateSearchIndex', 'app:GenerateSitemap'] as $command) {
            $this->assertContains($command, $called);
        }
        $this->assertNotContains('scout:import', $called, 'not registered inside a queued job');
    }
}
