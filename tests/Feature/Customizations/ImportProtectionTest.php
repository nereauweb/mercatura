<?php

declare(strict_types=1);

namespace Tests\Feature\Customizations;

use App\Models\Customizations\Customization;
use App\Support\Connectors\BaseConnector;
use App\Support\Connectors\ConnectorCommand;
use App\Support\ImportConnectors;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CustomizationFixture;
use Tests\TestCase;

/** v2c.6: imports write through upsertCustomization(); locked and manual rows are left alone. */
final class ImportProtectionTest extends TestCase
{
    use DatabaseTransactions;

    private function command(): UpsertProbe
    {
        return new UpsertProbe;
    }

    public function test_manual_locked_and_importable_rows(): void
    {
        $this->seed(CoreSeeder::class);
        $f = CustomizationFixture::create();
        $keys = ['source' => 'own', 'pipeline' => 'own', 'source_product_sku' => $f->product->sku, 'source_variant_sku' => $f->a->sku, 'technique_label' => 'Serigrafia', 'position_label' => 'Fronte'];

        $this->assertTrue($f->screenA->isManual(), 'no connector owns "own"');
        $this->assertTrue($f->screenA->isProtectedFromImport());
        $this->assertNull($this->command()->upsert($keys, ['processing_days' => 9]), 'a manual row is never overwritten');
        $this->assertSame(5, (int) $f->screenA->fresh()?->processing_days);

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
        $this->assertFalse($f->screenA->isManual());
        $this->assertFalse($f->screenA->isProtectedFromImport());
        $this->assertNotNull($this->command()->upsert($keys, ['processing_days' => 9]), 'a connector row is updated by its import');
        $this->assertSame(9, (int) $f->screenA->fresh()?->processing_days);

        $f->screenA->update(['locked' => true]);
        $this->assertNull($this->command()->upsert($keys, ['processing_days' => 3]), 'locked: skipped');
        $this->assertSame(9, (int) $f->screenA->fresh()?->processing_days);
        $this->assertSame([$f->embroideryA->id, $f->screenB->id], Customization::query()->importable()->where('product_id', $f->product->id)->orderBy('id')->pluck('id')->all());

        $created = $this->command()->upsert(array_merge($keys, ['technique_label' => 'Tampografia']), ['product_id' => $f->product->id, 'variant_id' => $f->a->id]);
        $this->assertInstanceOf(Customization::class, $created, 'a new row is created as before');
    }
}

/** Exposes the protected helper of ConnectorCommand. */
final class UpsertProbe extends ConnectorCommand
{
    protected $signature = 'test:upsert';

    public function __construct()
    {
        parent::__construct();
        $this->setOutput(new \Illuminate\Console\OutputStyle(new \Symfony\Component\Console\Input\ArrayInput([]), new \Symfony\Component\Console\Output\NullOutput));
    }

    /**
     * @param  array<string, mixed>  $keys
     * @param  array<string, mixed>  $values
     */
    public function upsert(array $keys, array $values): ?Customization
    {
        return $this->upsertCustomization($keys, $values);
    }
}
