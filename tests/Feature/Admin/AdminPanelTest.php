<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Messages\Pages\ListMessages;
use App\Filament\Resources\Messages\Pages\ViewMessage;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Quotations\Pages\ListQuotations;
use App\Filament\Resources\Quotations\Pages\ViewQuotation;
use App\Filament\Widgets\SalesOverviewWidget;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Quotation;
use App\Models\User;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/** v2b.1 stop criterion: /admin logs an admin in and shows the sales lists; the legacy admin still answers. */
class AdminPanelTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private User $customerUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        $this->admin = User::query()->create(['name' => 'Admin', 'email' => 'panel-admin@example.com', 'password' => Hash::make('password')]);
        $this->admin->assignRole('admin');
        $this->customerUser = User::query()->create(['name' => 'Cliente', 'email' => 'panel-customer@example.com', 'password' => Hash::make('password')]);
        $this->customerUser->assignRole('customer');
    }

    public function test_guests_are_sent_to_the_panel_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        // livewire.inject_assets is false for the storefront: the panel must inject the runtime itself.
        $this->get('/admin/login')->assertOk()->assertSee(config('brand.name'))->assertSee('/livewire.js', false); // Livewire 4 serves it under /livewire-{hash}/
    }

    public function test_customers_cannot_enter_the_panel(): void
    {
        $this->actingAs($this->customerUser)->get('/admin')->assertForbidden();
    }

    public function test_admin_sees_the_dashboard_and_the_sales_lists(): void
    {
        $this->actingAs($this->admin);
        $this->get('/admin')->assertOk()->assertSee(__('admin.nav.sales'))->assertSee(__('admin.order.plural'));
        Livewire::test(SalesOverviewWidget::class)->assertOk()->assertSee(__('admin.dashboard.orders_to_process'));

        foreach ([ListOrders::class, ListQuotations::class, ListMessages::class, ListCustomers::class] as $page) {
            Livewire::test($page)->assertOk();
        }
    }

    public function test_admin_can_open_an_order_and_reading_marks_quotations_and_messages(): void
    {
        $this->actingAs($this->admin);
        $customer = Customer::query()->create(['user_id' => $this->customerUser->id, 'customer_type' => 'Azienda', 'email' => $this->customerUser->email, 'name' => 'Giulia', 'surname' => 'Bianchi', 'company' => 'ACME']);
        $order = Order::query()->create(['user_id' => $this->customerUser->id, 'customer_id' => $customer->id, 'items_price' => 100, 'delivery_cost' => 16, 'total_price' => 116, 'total_tax' => 25.52, 'total_taxed_price' => 141.52, 'status' => 'requested', 'payment_method' => 'bank_transfer', 'payment_status' => 'unpaid', 'address' => 'Via Roma 1', 'city' => 'Roma', 'province' => 'RM', 'zip_code' => '00100', 'country' => 'IT']);
        OrderItem::query()->create(['order_id' => $order->id, 'product_id' => 0, 'product_sku' => 'SKU-1', 'product_name' => 'Penna demo', 'quantity' => 100, 'price' => 100]);

        Livewire::test(ViewOrder::class, ['record' => $order->id])->assertOk()->assertSee('Penna demo')->assertSee('ACME')->assertSee('Richiesto');
        Livewire::test(ListOrders::class)->assertOk()->assertSee('ACME');

        $quotation = Quotation::query()->create(['customer_email' => 'q@example.com', 'customer_name' => 'Marco', 'customer_surname' => 'Verdi']);
        Livewire::test(ViewQuotation::class, ['record' => $quotation->id])->assertOk();
        $this->assertNotNull($quotation->fresh()?->read_at);

        $message = Message::query()->create(['email' => 'm@example.com', 'name' => 'Anna', 'surname' => 'Neri', 'subject' => 'Info', 'message' => 'Ciao']);
        Livewire::test(ViewMessage::class, ['record' => $message->id])->assertOk()->assertSee('Info');
        $this->assertNotNull($message->fresh()?->read_at);
    }

    public function test_resources_require_their_permission_not_only_the_role(): void
    {
        $limited = User::query()->create(['name' => 'Limitato', 'email' => 'panel-limited@example.com', 'password' => Hash::make('password')]);
        // A role that may enter the panel (the User::canAccessPanel check is on the admin role) but lacks orders.manage.
        $editor = \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
        $limited->assignRole($editor);
        $limited->syncPermissions([]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $editor->revokePermissionTo('orders.manage');

        $this->actingAs($limited)->get('/admin')->assertOk();
        $this->actingAs($limited)->get('/admin/orders')->assertForbidden();
    }
}
