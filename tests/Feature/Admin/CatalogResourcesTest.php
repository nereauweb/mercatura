<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Resources\Brands\Pages\ListBrands;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\ProductVariants\Pages\EditProductVariant;
use App\Filament\Resources\Taxonomy\Pages\ManageProductColors;
use App\Filament\Resources\Taxonomy\Pages\ManageProductSizes;
use App\Models\Category;
use App\Models\LegacyRedirect;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductSize;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\CoreSeeder;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** v2b.3 stop criterion: the catalogue is editable from Filament with the legacy side effects (docs/02_V2B_ADMIN.md §4.4). */
class CatalogResourcesTest extends TestCase
{
    use DatabaseTransactions;

    private Category $root;

    private Category $child;

    private ProductColor $color;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(CoreSeeder::class);
        $admin = User::query()->create(['name' => 'Admin', 'email' => 'catalog-admin@example.com', 'password' => Hash::make('password')]);
        $admin->assignRole('admin');
        $this->actingAs($admin);
        $this->root = Category::query()->create(['name' => 'Abbigliamento test', 'slug' => 'abbigliamento-test', 'active' => 1, 'position' => 99]);
        $this->child = Category::query()->create(['parent_id' => $this->root->id, 'name' => 'T-shirt test', 'slug' => 't-shirt-test', 'active' => 1, 'position' => 0]);
        $this->color = ProductColor::query()->create(['label' => 'Blu test', 'code' => '1E3A8A']);
    }

    private function createProduct(): Product
    {
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'sku' => 'TEST-001', 'name' => 'Maglietta test', 'active' => true, 'description' => '<p>Descrizione</p>',
                'forced_status' => 'none', 'isBestseller' => '0', 'isGreen' => '0', 'isPromo' => '0',
                'category_ids' => [$this->child->id],
                'variant.color_id' => $this->color->id, 'variant.size_id' => (int) config('mercatura.catalog.one_size_id'), 'variant.stock' => 10, 'variant.price' => 4.5,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        return Product::query()->where('sku', 'TEST-001')->firstOrFail();
    }

    public function test_lists_render(): void
    {
        foreach ([ListProducts::class, ListCategories::class, ListBrands::class, ManageProductColors::class, ManageProductSizes::class] as $page) {
            Livewire::test($page)->assertOk();
        }
    }

    public function test_create_product_makes_a_main_variant_and_attaches_the_parent_category(): void
    {
        $product = $this->createProduct();

        $this->assertNotNull($product->main_variant_id);
        $this->assertSame('4.50', (string) $product->variants_min_price);
        $this->assertSame(1, $product->variants()->count());
        $this->assertEqualsCanonicalizing([$this->root->id, $this->child->id], $product->categories()->pluck('categories.id')->all(), 'the parent is attached with the child');
        $this->assertSame('Maglietta-test-TEST-001', $product->slug);
    }

    public function test_edit_product_regenerates_slug_with_a_redirect_and_syncs_categories(): void
    {
        $product = $this->createProduct();

        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->fillForm(['name' => 'Maglietta rinominata', 'category_ids' => [], 'isGreen' => '2'])
            ->call('save')
            ->assertHasNoFormErrors();

        $product->refresh();
        $this->assertSame('Maglietta-rinominata-TEST-001', $product->slug);
        $this->assertSame(2, (int) $product->isGreen);
        $this->assertSame('/prodotti/Maglietta-rinominata-TEST-001', LegacyRedirect::for('/prodotti/Maglietta-test-TEST-001')?->to_path);
        $this->assertSame(0, $product->categories()->count());
    }

    public function test_variant_edit_updates_prices_attributes_and_reelects_the_main_variant(): void
    {
        $product = $this->createProduct();
        $variant = $product->variants()->firstOrFail();

        Livewire::test(EditProductVariant::class, ['record' => $variant->id])
            ->fillForm([
                'stock' => 25, 'isSale' => '1',
                'prices' => [['from_quantity' => 1, 'price' => 5, 'original_price' => 2.5, 'included_additional_costs' => 0], ['from_quantity' => 100, 'price' => 4, 'original_price' => 2.5, 'included_additional_costs' => 0]],
                'attribute_values' => [['attribute_id' => 4, 'value' => '100% cotone']],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $variant->refresh();
        $this->assertSame(25, (int) $variant->stock);
        $this->assertSame(2, $variant->prices()->count());
        $this->assertSame('100% cotone', $variant->attribute_value(4));
        $this->assertSame('4.00', (string) $product->refresh()->variants_min_price, 'main variant prices are recomputed');
    }

    public function test_bulk_status_change_and_category_assignment(): void
    {
        $product = $this->createProduct();
        $other = Category::query()->create(['parent_id' => $this->root->id, 'name' => 'Polo test', 'slug' => 'polo-test', 'active' => 1]);

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('deactivate', [$product->id])
            ->callTableBulkAction('assignCategory', [$product->id], ['category_ids' => [$other->id]]);

        $product->refresh();
        $this->assertFalse((bool) $product->active);
        $this->assertTrue($product->categories()->whereKey($other->id)->exists());
    }

    public function test_category_delete_cascades_like_the_legacy_admin(): void
    {
        $product = $this->createProduct();

        Livewire::test(EditCategory::class, ['record' => $this->root->id])->callAction(DeleteAction::class);

        $this->assertNotNull(Category::withTrashed()->find($this->root->id)?->deleted_at);
        $this->assertNotNull(Category::withTrashed()->find($this->child->id)?->deleted_at);
        $this->assertTrue($product->refresh()->exists, 'products are detached, never deleted');
        $this->assertSame(0, $product->categories()->count());
    }

    public function test_taxonomy_rows_in_use_cannot_be_deleted(): void
    {
        $this->createProduct();
        $this->assertGreaterThan(0, ProductVariant::query()->where('color_id', $this->color->id)->count());

        Livewire::test(ManageProductColors::class)->callTableAction(DeleteAction::class, $this->color)->assertNotified();
        $this->assertNotNull(ProductColor::query()->find($this->color->id), 'a colour used by a variant stays');

        $size = ProductSize::query()->create(['type_id' => 1, 'label' => 'Test-size']);
        Livewire::test(ManageProductSizes::class)->callTableAction(DeleteAction::class, $size);
        $this->assertNull(ProductSize::query()->find($size->id));
    }
}
