<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Resources\System\Pricing\Pages\ManageProductMarkups;
use App\Filament\Resources\System\Pricing\Pages\ManageProductPriceTiers;
use App\Models\ProductMarkup;
use App\Models\User;
use Database\Seeders\CoreSeeder;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Support\CustomizationFixture;
use Tests\TestCase;

/** Sistema → Regole di prezzo: the markup bands and quantity tiers are editable and act on the storefront at once. */
class PricingRulesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        $admin = User::query()->create(['name' => 'Admin', 'email' => 'pricing-admin@example.com', 'password' => Hash::make('password')]);
        $admin->assignRole('admin');
        $this->actingAs($admin);
    }

    public function test_bands_are_listed_edited_and_kept_disjoint(): void
    {
        $band = ProductMarkup::query()->where('condition_1', 600)->firstOrFail();
        $this->assertSame(30, (int) $band->value);

        $page = Livewire::test(ManageProductMarkups::class);
        $page->assertOk()->assertSee('600');
        $page->callTableAction(EditAction::class, $band, ['condition_1' => 600, 'condition_2' => 1000, 'value' => 40])->assertHasNoActionErrors();
        $this->assertSame(40, (int) $band->fresh()?->value);

        // A fresh component per call: a mounted action would otherwise capture the next one as its nested modal action.
        Livewire::test(ManageProductMarkups::class)->callAction(TestAction::make('create')->table(), data: ['condition_1' => 900, 'condition_2' => 1200, 'value' => 33])->assertHasActionErrors(['condition_2']);
        Livewire::test(ManageProductMarkups::class)->callAction(TestAction::make('create')->table(), data: ['condition_1' => 100000000, 'condition_2' => 200000000, 'value' => 5])->assertHasNoActionErrors();
        $this->assertSame(1, ProductMarkup::query()->where('condition_1', 100000000)->count());
        Livewire::test(ManageProductMarkups::class)->callAction(TestAction::make('create')->table(), data: ['condition_1' => 50, 'condition_2' => 40, 'value' => 5])->assertHasActionErrors(['condition_2']);

        Livewire::test(ManageProductPriceTiers::class)->assertOk();
    }

    public function test_changing_a_band_changes_the_configurator_total_at_once(): void
    {
        $f = CustomizationFixture::create();
        $payload = ['articles' => [[$f->a->id, 100]], 'printings' => [], 'has_packaging' => 0];
        $this->assertSame('1.040,00&nbsp;&euro;', $this->postJson('/prodotti/configuratore/articoli', $payload)->json('total_price'), '100 × (8 + 30 %)');

        $band = ProductMarkup::query()->where('condition_1', 600)->firstOrFail();
        Livewire::test(ManageProductMarkups::class)->callTableAction(EditAction::class, $band, ['condition_1' => 600, 'condition_2' => 1000, 'value' => 40])->assertHasNoActionErrors();

        $this->assertSame('1.120,00&nbsp;&euro;', $this->postJson('/prodotti/configuratore/articoli', $payload)->json('total_price'), '100 × (8 + 40 %)');
    }
}
