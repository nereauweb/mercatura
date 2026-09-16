<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Actions\Orders\StoreOrderItemCustomizations;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\ProductVariants\Pages\EditProductVariant;
use App\Filament\Resources\ProductVariants\RelationManagers\CustomizationsRelationManager;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\Customizations\LinePricer;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Support\CustomizationFixture;
use Tests\TestCase;

/** v2c.4: the admin reads the order snapshot and the typed extras, and the variant's customization family. */
class OrderCustomizationsViewTest extends TestCase
{
    use DatabaseTransactions;

    public function test_order_view_shows_the_snapshot_and_the_extras(): void
    {
        $this->seed(CoreSeeder::class);
        $f = CustomizationFixture::create();
        $admin = User::query()->create(['name' => 'Admin', 'email' => 'orders-admin@example.com', 'password' => Hash::make('password')]);
        $admin->assignRole('admin');
        $this->actingAs($admin);
        $address = CustomerAddress::query()->create(['address' => 'Via Test 1', 'city' => 'Città', 'province' => 'XX', 'zip_code' => '00000', 'country' => 'Italia']);
        $customer = Customer::query()->create(['user_id' => $admin->id, 'customer_type' => 'Azienda', 'email' => $admin->email, 'name' => 'A', 'surname' => 'B', 'billing_address_id' => $address->id, 'shipping_address_id' => $address->id]);
        $order = Order::query()->create(['user_id' => $admin->id, 'customer_id' => $customer->id, 'items_price' => 0, 'delivery_cost' => 0, 'total_price' => 0, 'total_tax' => 0, 'total_taxed_price' => 0, 'status' => 'requested', 'payment_method' => 'bank_transfer', 'payment_status' => 'unpaid']);
        $item = OrderItem::query()->create(['order_id' => $order->id, 'product_id' => $f->product->id, 'product_sku' => $f->product->sku, 'product_name' => $f->product->name, 'quantity' => 20, 'price' => 471]);
        $line = app(LinePricer::class)->price([[$f->a->id, 20]], [$f->screenOneColorA->id], false);
        app(StoreOrderItemCustomizations::class)->handle($item, $line);

        Livewire::test(ViewOrder::class, ['record' => $order->id])->assertOk()
            ->assertSee('Serigrafia')->assertSee('Fronte')->assertSee('10x10')->assertSee('1 colore')
            ->assertSee('Setup Serigrafia Fronte')->assertSee(__('admin.order.extra_types.surcharge'))->assertSee(__('admin.order.artwork_missing'));
    }

    public function test_variant_edit_shows_the_customization_family(): void
    {
        $this->seed(CoreSeeder::class);
        $f = CustomizationFixture::create();
        $f->embroideryA->update(['family' => 'embroidery']);
        $admin = User::query()->create(['name' => 'Admin', 'email' => 'variants-admin@example.com', 'password' => Hash::make('password')]);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        Livewire::test(CustomizationsRelationManager::class, ['ownerRecord' => $f->a, 'pageClass' => EditProductVariant::class])->assertOk()->assertSee('embroidery')->assertSee('Ricamo');
    }
}
