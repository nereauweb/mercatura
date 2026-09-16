<?php

declare(strict_types=1);

namespace Tests\Feature\Customizations;

use App\Models\Customizations\Customization;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CustomizationFixture;
use Tests\TestCase;

/** cleanup:customizations deletes what no import touched for the configured days (children cascade), never on a dry run. */
final class CleanupCustomizationsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_retention_comes_from_config_and_children_cascade(): void
    {
        $this->seed(CoreSeeder::class);
        $f = CustomizationFixture::create();
        Customization::query()->whereKey($f->embroideryA->id)->update(['updated_at' => now()->subDays(100)]);
        Customization::query()->whereKey($f->screenA->id)->update(['updated_at' => now()->subDays(50)]);
        config(['mercatura.customizations.cleanup_days' => 90]);

        $this->artisan('cleanup:customizations')->expectsOutputToContain('Stale customizations: 0')->assertSuccessful();
        $this->assertNotNull(Customization::query()->find($f->embroideryA->id), 'manual rows (no connector owns "own") are never cleaned');

        $this->app->make(\App\Support\ImportConnectors::class)->register(new class extends \App\Support\Connectors\BaseConnector
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
        Customization::query()->whereKey($f->screenB->id)->update(['updated_at' => now()->subDays(100), 'locked' => true]);

        $this->artisan('cleanup:customizations', ['--dry-run' => true])->expectsOutputToContain('Stale customizations: 1')->assertSuccessful();
        $this->assertNotNull(Customization::query()->find($f->embroideryA->id), 'dry run deletes nothing');

        $this->artisan('cleanup:customizations')->expectsOutputToContain('Deleted: 1')->assertSuccessful();
        $this->assertNull(Customization::query()->find($f->embroideryA->id));
        $this->assertNull($f->embroideryOptionA->fresh(), 'areas, options and tiers cascade');
        $this->assertNotNull(Customization::query()->find($f->screenA->id), '50 days old: kept with a 90-day retention');
        $this->assertNotNull(Customization::query()->find($f->screenB->id), '100 days old but locked: kept');

        $this->artisan('cleanup:customizations', ['--days' => 30])->expectsOutputToContain('Deleted: 1')->assertSuccessful();
        $this->assertNull(Customization::query()->find($f->screenA->id), 'the option overrides the config');
    }
}
