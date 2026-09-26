<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Console\Commands\CatalogColorCodes;
use App\Filament\Resources\Taxonomy\Pages\ManageProductColorFamilies;
use App\Filament\Resources\Taxonomy\Pages\ManageProductColors;
use App\Models\ProductColor;
use App\Models\ProductColorFamily;
use App\Models\User;
use Database\Seeders\CoreSeeder;
use Filament\Actions\EditAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Colours belong to any number of families; missing hex codes come from the configured map. */
class ColorTaxonomyTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);
    }

    public function test_a_colour_can_sit_in_several_families(): void
    {
        $blue = ProductColorFamily::query()->create(['label' => 'Blu', 'code' => '0000FF']);
        $white = ProductColorFamily::query()->create(['label' => 'Bianco', 'code' => 'FFFFFF']);
        $color = ProductColor::query()->create(['label' => 'Blu/bianco test', 'code' => '1F3A93/FFFFFF']);

        Livewire::test(ManageProductColors::class)
            ->callTableAction(EditAction::class, $color, ['label' => 'Blu/bianco test', 'code' => '1F3A93/FFFFFF', 'families' => [$blue->id, $white->id]])
            ->assertHasNoTableActionErrors();
        $this->assertEqualsCanonicalizing([$blue->id, $white->id], $color->fresh()->families()->pluck('product_colors_families.id')->all());
        $this->assertSame(1, $blue->colors()->count());
        Livewire::test(ManageProductColorFamilies::class)->assertOk()->assertSee('Blu');
        Livewire::test(ManageProductColors::class)->assertOk()->assertSee('Bianco');

        $color->families()->sync([$white->id]);
        $this->assertSame(0, $blue->fresh()->colors()->count());
    }

    public function test_missing_colour_codes_are_filled_from_the_map_and_existing_ones_kept(): void
    {
        $map = ['blu' => '1F3A93', 'bianco' => 'FFFFFF', 'blu royal' => '2F4F8F', 'blu notte' => '0B1F3A', 'royal' => '2F4F8F'];
        $this->assertSame('0B1F3A', CatalogColorCodes::resolve('Blu-notte', $map), 'a hyphen inside a known two-word colour is not a split');
        $this->assertSame('2F4F8F', CatalogColorCodes::resolve('Blu-royal', $map));
        $this->assertSame('FFFFFF/2F4F8F', CatalogColorCodes::resolve('Bianco/blu royal', $map));
        $this->assertSame('1F3A93', CatalogColorCodes::resolve('Blu', $map));
        $this->assertSame('2F4F8F', CatalogColorCodes::resolve('Blu royal', $map), 'multi-word labels match as a whole first');
        $this->assertSame('1F3A93/FFFFFF', CatalogColorCodes::resolve('Blu/bianco', $map));
        $this->assertSame('1F3A93/FFFFFF', CatalogColorCodes::resolve('Blu - Bianco', $map));
        $this->assertNull(CatalogColorCodes::resolve('Blu/ottanio', $map), 'a part outside the map leaves the code empty');
        $this->assertNull(CatalogColorCodes::resolve('Ottanio', $map));

        config(['mercatura.catalog.color_codes' => $map]);
        $plain = ProductColor::query()->create(['label' => 'Blu', 'code' => '']);
        $composite = ProductColor::query()->create(['label' => 'Blu-bianco', 'code' => null]);
        $unknown = ProductColor::query()->create(['label' => 'Ottanio', 'code' => '']);
        $kept = ProductColor::query()->create(['label' => 'Bianco', 'code' => 'FAFAFA']);

        $this->artisan('catalog:color-codes', ['--dry-run' => true])->expectsOutputToContain('2 filled, 1 without a match (Ottanio)')->assertSuccessful();
        $this->assertSame('', (string) $plain->fresh()->code, 'dry run writes nothing');
        $this->artisan('catalog:color-codes')->expectsOutputToContain('2 filled')->assertSuccessful();
        $this->assertSame('1F3A93', $plain->fresh()->code);
        $this->assertSame('1F3A93/FFFFFF', $composite->fresh()->code);
        $this->assertSame('', (string) $unknown->fresh()->code);
        $this->assertSame('FAFAFA', $kept->fresh()->code, 'codes already set are never touched');
    }
}
