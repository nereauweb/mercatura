<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Support\Connectors\BaseConnector;
use App\Support\ImportConnectors;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** "Stato forzato: Attivo" survives the import, even when the supplier stops listing the product. */
final class ForcedActiveImportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_product_forced_active_stays_active_when_it_leaves_the_feed(): void
    {
        $registry = new ImportConnectors;
        $registry->register(new class extends BaseConnector
        {
            public function key(): string
            {
                return 'acme';
            }

            public function label(): string
            {
                return 'ACME Gadgets';
            }
        });
        $this->app->instance(ImportConnectors::class, $registry);

        $forced = Product::query()->create(['sku' => 'FRC-1', 'source' => 'acme', 'source_sku' => 'FRC-1', 'name' => 'Forzato', 'slug' => 'forzato-1', 'active' => 1, 'forced_status' => 'active']);
        $plain = Product::query()->create(['sku' => 'FRC-2', 'source' => 'acme', 'source_sku' => 'FRC-2', 'name' => 'Normale', 'slug' => 'normale-2', 'active' => 1, 'forced_status' => 'none']);

        $this->artisan('app:DisableWrongProducts')->assertSuccessful();

        $this->assertTrue((bool) $forced->fresh()->active, 'the admin forced it active');
        $this->assertFalse((bool) $plain->fresh()->active, 'not in the feed any more');
    }
}
