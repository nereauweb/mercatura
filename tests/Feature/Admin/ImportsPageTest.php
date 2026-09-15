<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Pages\Imports;
use App\Filament\Resources\System\CategoryImportAliases\Pages\ManageCategoryImportAliases;
use App\Jobs\ImportProductsJob;
use App\Models\Category;
use App\Models\CategoryImportAlias;
use App\Models\User;
use App\Support\Connectors\BaseConnector;
use App\Support\ImportConnectors;
use Database\Seeders\CoreSeeder;
use Filament\Actions\EditAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/** v2b.5 stop criterion: with connectors off the page explains why; with a flag on the legacy jobs are dispatched unchanged. */
class ImportsPageTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        $admin = User::query()->create(['name' => 'Admin', 'email' => 'imports-admin@example.com', 'password' => Hash::make('password')]);
        $admin->assignRole('admin');
        $this->actingAs($admin);
    }

    public function test_without_connectors_the_page_shows_the_notice_and_no_dispatch(): void
    {
        $page = Livewire::test(Imports::class);
        $page->assertOk();
        $page->assertSee(__('admin.imports.no_connector'))->assertActionHidden('dispatchProducts');
    }

    public function test_with_a_connector_enabled_the_product_import_job_is_queued_with_its_flags(): void
    {
        // A neutral connector registered by the test: the core suite runs with no package installed.
        $this->app->make(ImportConnectors::class)->register(new class extends BaseConnector
        {
            public function key(): string
            {
                return 'acme';
            }

            public function label(): string
            {
                return 'Acme';
            }
        });
        config(['mercatura.features.connectors.acme' => true]);
        Queue::fake();

        $page = Livewire::test(Imports::class);
        $page->assertOk();
        $page->assertDontSee(__('admin.imports.no_connector'))
            ->callAction('dispatchProducts', ['process_source' => 'acme', 'download_data' => true, 'update_live' => false, 'process_product_data' => true, 'full_products_update' => true, 'update_categories' => false])
            ->assertHasNoActionErrors()->assertNotified();

        Queue::assertPushed(ImportProductsJob::class, 1);
    }

    public function test_category_aliases_can_be_assigned(): void
    {
        $category = Category::query()->create(['name' => 'Alias target', 'slug' => 'alias-target', 'active' => 1]);
        $alias = CategoryImportAlias::query()->create(['source' => 'acme', 'parent_category_ref' => 'Abbigliamento', 'category_ref' => 'T-shirt', 'category_id' => null]);

        $page = Livewire::test(ManageCategoryImportAliases::class);
        $page->assertOk();
        $page->callTableAction(EditAction::class, $alias, ['category_id' => $category->id])->assertHasNoTableActionErrors();

        $this->assertSame($category->id, $alias->fresh()?->category_id);
    }
}
