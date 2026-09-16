<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Resources\ProductVariants\Pages\EditProductVariant;
use App\Filament\Resources\ProductVariants\RelationManagers\CustomizationsRelationManager;
use App\Models\Customizations\Customization;
use App\Models\User;
use App\Support\Customizations\LinePricer;
use Database\Seeders\CoreSeeder;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Support\CustomizationFixture;
use Tests\TestCase;

/** v2c.6 stop criterion: a customization can be created, edited, set default and locked from the variant page. */
class CustomizationsRelationManagerTest extends TestCase
{
    use DatabaseTransactions;

    private CustomizationFixture $f;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        $this->f = CustomizationFixture::create();
        $admin = User::query()->create(['name' => 'Admin', 'email' => 'customizations-admin@example.com', 'password' => Hash::make('password')]);
        $admin->assignRole('admin');
        $this->actingAs($admin);
    }

    private function manager(): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(CustomizationsRelationManager::class, ['ownerRecord' => $this->f->b, 'pageClass' => EditProductVariant::class]);
    }

    public function test_a_manual_customization_is_created_with_its_tree_and_priced(): void
    {
        $manager = $this->manager();
        $manager->assertOk();
        $manager->assertCanSeeTableRecords([$this->f->screenB]);
        $manager->callAction(TestAction::make('create')->table(), data: [
            'technique_label' => 'Incisione laser', 'position_label' => 'Lato', 'family' => 'engraving', 'minimum_quantity' => 10, 'processing_days' => 4, 'has_packaging' => false,
            'areas' => [[
                'label' => '5x5', 'type' => 'rectangle', 'width_mm' => 50, 'height_mm' => 50,
                'options' => [[
                    'label' => 'Incisione', 'number_of_colors' => 1, 'setup' => 20, 'setup_multiplier' => 1, 'start_cost' => 0,
                    'tiers' => [['from_quantity' => 1, 'original_price' => 1.00, 'price' => 1.50], ['from_quantity' => 100, 'original_price' => 0.50, 'price' => 0.75]],
                ]],
            ]],
        ])->assertHasNoActionErrors();

        $created = Customization::query()->where('variant_id', $this->f->b->id)->where('technique_label', 'Incisione laser')->firstOrFail();
        $this->assertSame(['own', null, 'engraving', 0, $this->f->product->id], [$created->source, $created->pipeline, $created->family, (int) $created->is_default, (int) $created->product_id]);
        $this->assertTrue($created->isManual());
        $option = $created->areas()->firstOrFail()->options()->firstOrFail();
        $this->assertSame(2, $option->tiers()->count());

        // 100 pieces: article 100 × 10.40 + engraving 100 × (0.50 × 1.30) + setup 20 = 1 125.
        $line = app(LinePricer::class)->price([[$this->f->b->id, 100]], [$option->id], false);
        $this->assertEqualsWithDelta(1125.0, $line->price, 0.001);
        $this->assertSame('LATO - Incisione laser  5x5 Incisione', $line->customizations[0]->label);
    }

    public function test_set_default_lock_and_delete(): void
    {
        $manual = Customization::query()->create(['source' => 'own', 'product_id' => $this->f->product->id, 'variant_id' => $this->f->b->id, 'technique_label' => 'Ricamo', 'position_label' => 'Retro', 'is_default' => 0]);

        $this->manager()->callTableAction('setDefault', $manual)->assertNotified();
        $this->assertSame(1, (int) $manual->fresh()?->is_default);
        $this->assertSame(0, (int) $this->f->screenB->fresh()?->is_default, 'one default per variant');

        $this->manager()->assertTableActionHidden('toggleLock', $manual);
        // The fixture rows are manual too until a connector claims "own".
        $this->manager()->assertTableActionHidden('toggleLock', $this->f->screenB);
        // Lock is offered on rows a connector owns, so register one first.
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
        $this->manager()->callTableAction('toggleLock', $this->f->screenB)->assertHasNoTableActionErrors();
        $this->assertTrue((bool) $this->f->screenB->fresh()?->locked);

        $this->manager()->callTableAction(DeleteAction::class, $manual);
        $this->assertNull($manual->fresh());
    }
}
