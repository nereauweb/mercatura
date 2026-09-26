<?php

declare(strict_types=1);

namespace Tests\Feature\Customizations;

use App\Models\Customizations\Customization;
use App\Support\Connectors\BaseConnector;
use App\Support\Connectors\ConnectorCommand;
use App\Support\ImportConnectors;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\CustomizationFixture;
use Tests\TestCase;

/** docs/03_CUSTOMIZATIONS.md §7: rows the source stops listing are deactivated, reactivated when listed again. */
class CustomizationLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    private function ownConnector(): void
    {
        $this->app->make(ImportConnectors::class)->register(new class extends BaseConnector
        {
            public function key(): string
            {
                return 'own';
            }

            public function label(): string
            {
                return 'Own';
            }
        });
    }

    public function test_the_storefront_reads_only_active_rows_and_the_admin_reads_all(): void
    {
        $this->seed(CoreSeeder::class);
        $f = CustomizationFixture::create();
        $this->assertSame(2, $f->a->customizations()->count());

        Customization::query()->whereKey($f->screenA->id)->update(['active' => false]);
        $this->assertSame(1, $f->a->customizations()->count(), 'storefront relation on the variant');
        $this->assertSame(2, $f->a->allCustomizations()->count(), 'admin relation');
        $this->assertSame(2, $f->product->customizations()->count(), 'the product relation is filtered too (B stays)');
        $this->assertFalse($f->product->customizations()->whereKey($f->screenA->id)->exists());
    }

    public function test_cleanup_deactivates_rows_not_listed_and_reactivates_rows_listed_again(): void
    {
        $this->seed(CoreSeeder::class);
        $this->ownConnector();
        $f = CustomizationFixture::create();
        config(['mercatura.customizations.missing_days' => 3, 'mercatura.customizations.cleanup_days' => 90]);
        Customization::query()->whereKey($f->screenA->id)->update(['last_seen_at' => now()->subDays(10)]);
        Customization::query()->whereKey($f->embroideryA->id)->update(['last_seen_at' => now()->subDays(1)]);
        Customization::query()->whereKey($f->screenB->id)->update(['last_seen_at' => now()->subDays(10), 'locked' => true]);

        $this->artisan('cleanup:customizations', ['--dry-run' => true])->expectsOutputToContain('to deactivate: 1')->assertSuccessful();
        $this->assertTrue($f->screenA->fresh()->active, 'dry run changes nothing');

        $this->artisan('cleanup:customizations')->expectsOutputToContain('to deactivate: 1')->assertSuccessful();
        $this->assertFalse($f->screenA->fresh()->active, 'not listed for 10 days');
        $this->assertTrue($f->embroideryA->fresh()->active, 'listed yesterday');
        $this->assertTrue($f->screenB->fresh()->active, 'locked rows are never touched');
        $this->assertNotNull($f->screenA->fresh(), 'deactivated, not deleted: 10 days is within the 90-day retention');

        // Listed again by the source: an upsert (or a touch) marks it seen, the cleanup switches it back on.
        Customization::query()->whereKey($f->screenA->id)->update(['last_seen_at' => now()]);
        $this->artisan('cleanup:customizations')->expectsOutputToContain('to reactivate: 1')->assertSuccessful();
        $this->assertTrue($f->screenA->fresh()->active);

        // Never listed again for the retention period: deleted.
        Customization::query()->whereKey($f->screenA->id)->update(['last_seen_at' => now()->subDays(100)]);
        $this->artisan('cleanup:customizations')->expectsOutputToContain('Deleted: 1')->assertSuccessful();
        $this->assertNull(Customization::query()->find($f->screenA->id));

        config(['mercatura.customizations.cleanup_days' => 0]);
        Customization::query()->whereKey($f->embroideryA->id)->update(['last_seen_at' => now()->subDays(400)]);
        $this->artisan('cleanup:customizations')->expectsOutputToContain('Stale customizations: 0')->assertSuccessful();
        $this->assertNotNull(Customization::query()->find($f->embroideryA->id), 'retention 0 never deletes');
        $this->assertFalse($f->embroideryA->fresh()->active, 'but it is switched off');
    }

    public function test_connector_commands_can_skip_a_variant_whose_fingerprint_did_not_change(): void
    {
        $this->seed(CoreSeeder::class);
        $this->ownConnector();
        $f = CustomizationFixture::create();
        $command = new class extends ConnectorCommand
        {
            protected $signature = 'own:printing-test';

            public function handle(): void {}

            public function fingerprint(array $inputs): string
            {
                return $this->customizationFingerprint($inputs);
            }

            public function unchanged(string $sku, string $hash): bool
            {
                return $this->customizationsUnchanged('own', $sku, $hash, 'own');
            }

            public function touch(string $sku): int
            {
                return $this->touchCustomizations('own', $sku, 'own');
            }

            public function upsert(array $keys, array $values): ?Customization
            {
                return $this->upsertCustomization($keys, $values);
            }
        };
        Artisan::registerCommand($command);

        $hash = $command->fingerprint(['rows' => [1, 2, 3]]);
        $this->assertSame($hash, $command->fingerprint(['rows' => [1, 2, 3]]), 'deterministic');
        $this->assertNotSame($hash, $command->fingerprint(['rows' => [1, 2, 4]]), 'the supplier rows are part of it');
        \App\Models\ProductMarkup::query()->first()?->update(['value' => 99]);
        app(\App\Support\Connectors\MarkupRules::class)->flush();
        $this->assertNotSame($hash, $command->fingerprint(['rows' => [1, 2, 3]]), 'the markup bands are part of it');
        $hash = $command->fingerprint(['rows' => [1, 2, 3]]);

        $sku = $f->a->sku;
        $this->assertFalse($command->unchanged($sku, $hash), 'no fingerprint stored yet');
        Customization::query()->where('source_variant_sku', $sku)->update(['source_hash' => $hash, 'active' => false, 'last_seen_at' => now()->subDays(30)]);
        $this->assertTrue($command->unchanged($sku, $hash));
        $this->assertSame(2, $command->touch($sku));
        $this->assertTrue($f->screenA->fresh()->active, 'touched rows are seen and active again');
        $this->assertTrue($f->screenA->fresh()->last_seen_at->isToday());

        Customization::query()->whereKey($f->screenA->id)->update(['locked' => true, 'source_hash' => 'other']);
        $this->assertTrue($command->unchanged($sku, $hash), 'locked rows are not the import\'s business');

        Customization::query()->whereKey($f->embroideryA->id)->update(['active' => false, 'last_seen_at' => now()->subDays(30)]);
        $row = $command->upsert(['source' => 'own', 'source_product_sku' => $f->product->sku, 'source_variant_sku' => $sku, 'technique_label' => $f->embroideryA->technique_label, 'position_label' => $f->embroideryA->position_label], ['processing_days' => 7]);
        $this->assertSame($f->embroideryA->id, $row?->id);
        $this->assertTrue($row->active, 'an upsert reactivates');
        $this->assertTrue($row->last_seen_at->isToday());
    }
}
