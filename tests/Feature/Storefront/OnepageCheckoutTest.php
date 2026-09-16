<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Http\Livewire\FrontendCheckoutOnepage;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemCustomization;
use App\Models\User;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Support\CustomizationFixture;
use Tests\TestCase;

/** docs/04_STOREFRONT_FLOWS.md §4.7: the onepage checkout produces the same order as the steps flow. */
final class OnepageCheckoutTest extends TestCase
{
    use DatabaseTransactions;

    private CustomizationFixture $f;

    /** @var array<string, array<string, mixed>> */
    private array $sessionCart;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        $this->f = CustomizationFixture::create();
        $this->sessionCart = ['line' => ['articles' => [[$this->f->a->id, 100]], 'customizations' => [$this->f->screenOneColorA->id], 'has_packaging' => 0, 'sample' => 0]];
        config(['mercatura.storefront.checkout' => 'onepage', 'mercatura.checkout.payment_methods' => ['bank_transfer'], 'mercatura.storefront.shipping_date' => true]);
    }

    /** @return array<string, string> */
    private function registration(): array
    {
        return [
            'name' => 'Mario', 'surname' => 'Rossi', 'email' => 'onepage@example.com', 'phone' => '0123456789', 'password' => 'password123', 'password_confirmation' => 'password123',
            'customer_type' => 'Azienda', 'company' => 'ACME srl', 'activity' => 'Commercio', 'vat_code' => '01234567890', 'pec' => 'acme@pec.example.com', 'sdi_code' => 'ABCDEFG',
            'bill_address' => 'Via Roma 1', 'bill_city' => 'Milano', 'bill_province' => 'MI', 'bill_zip_code' => '20100', 'bill_country' => 'Italia',
            'consent_gdpr' => '1', 'consent_terms' => '1', 'subscribe_newsletter' => '0',
        ];
    }

    public function test_the_cart_and_the_checkout_switch_to_the_onepage_flow(): void
    {
        $this->withSession(['cart' => $this->sessionCart])->post('/carrello/procedi')->assertRedirect(route('frontend.checkout.onepage'));
        $html = $this->withSession(['cart' => $this->sessionCart])->get('/checkout')->assertOk()->getContent();
        $this->assertStringContainsString('data-onepage-step="1"', $html);
        $this->assertStringContainsString(__('frontend.onepage.steps.method'), $html);
        $this->assertStringContainsString('name="customer_type"', $html, 'the registration form is inline');
        $this->assertStringContainsString(__('frontend.onepage.your_checkout'), $html);

        $cart = $this->withSession(['cart' => $this->sessionCart])->get('/carrello/riepilogo')->assertOk()->getContent();
        $this->assertStringContainsString(__('frontend.cart.table.subtotal'), $cart);
        $this->assertStringContainsString(__('frontend.cart.table.clear'), $cart);
        $this->assertStringContainsString('Serigrafia', $cart, 'customization lines are listed under the product');
        $this->withSession(['cart' => $this->sessionCart])->post('/carrello/svuota')->assertRedirect(route('frontend.cart.index'));
        $this->assertSame([], session('cart'));

        $this->withSession(['cart' => []])->get('/checkout')->assertRedirect(route('frontend.cart.index'));
        config(['mercatura.storefront.checkout' => 'steps']);
        $this->withSession(['cart' => $this->sessionCart])->post('/carrello/procedi')->assertRedirect(route('frontend.checkout.account'));
        $this->withSession(['cart' => $this->sessionCart])->get('/checkout')->assertRedirect(route('frontend.checkout.account'));
    }

    public function test_a_guest_registers_fills_the_steps_and_places_the_order(): void
    {
        session(['cart' => $this->sessionCart]);
        $component = Livewire::test(FrontendCheckoutOnepage::class)
            ->assertSet('step', 1)
            ->call('login', ['email' => 'nobody@example.com', 'password' => 'wrong'])
            ->assertHasErrors(['auth'])
            ->call('register', ['email' => 'bad'])
            ->assertHasErrors(['email', 'name', 'bill_address'])
            ->call('register', $this->registration())
            ->assertHasNoErrors()
            ->assertSet('step', 2);
        $user = User::query()->where('email', 'onepage@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('customer'));
        $this->assertTrue(auth()->check());
        $this->assertSame('ACME srl', $user->customer->company);

        $component->call('saveBilling', ['name' => 'Mario', 'surname' => 'Rossi', 'email' => 'onepage@example.com', 'phone' => '0123456789', 'customer_type' => 'Azienda', 'company' => 'ACME srl', 'vat_code' => '01234567890', 'pec' => 'acme@pec.example.com', 'bill_address' => 'Via Verdi 2', 'bill_city' => 'Torino', 'bill_province' => 'TO', 'bill_zip_code' => '10100', 'bill_country' => 'Italia', 'ship_to_billing' => '1'])
            ->assertHasNoErrors()
            ->assertSet('step', 4, 'shipping to the billing address skips step 3');
        $customer = Customer::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Torino', $customer->billing_address->city);
        $this->assertSame('Torino', $customer->shipping_address->city, 'billing copied to shipping');

        $component->call('goTo', 3)->assertSet('step', 3)
            ->call('saveShipping', ['shipping_address' => 'Via Po 3', 'shipping_city' => 'Genova', 'shipping_province' => 'GE', 'shipping_zip_code' => '16100', 'shipping_country' => 'Italia'])
            ->assertHasNoErrors()->assertSet('step', 4);
        $this->assertSame('Genova', $customer->shipping_address->fresh()->city);

        $component->call('saveShippingMethod')->assertSet('step', 5)
            ->call('savePayment')->assertHasErrors(['paymentMethod'])
            ->set('paymentMethod', 'bank_transfer')->call('savePayment')->assertSet('step', 6)
            ->assertSee(__('frontend.onepage.place_order'))->assertSee('Serigrafia')
            ->call('placeOrder', [])->assertHasErrors(['consent_gdpr', 'consent_terms'])
            ->call('placeOrder', ['consent_gdpr' => '1', 'consent_terms' => '1'])
            ->assertHasNoErrors()
            ->assertRedirect(route('frontend.checkout.finalized_page'));

        $order = Order::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('bank_transfer', $order->payment_method);
        $this->assertSame('Genova', $order->city, 'the order ships to the shipping address');
        $item = OrderItem::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(100, (int) $item->quantity);
        $this->assertNotNull($item->shipping_date);
        $this->assertSame(1, OrderItemCustomization::query()->where('item_id', $item->id)->count());
        $this->assertSame([], session('cart'), 'the cart is cleared after a bank transfer');
        $this->get(route('frontend.checkout.finalized_page'))->assertOk()->assertSee(__('frontend.checkout.bank_title'));
    }

    public function test_a_logged_customer_starts_at_the_billing_step_and_an_admin_is_blocked(): void
    {
        $user = User::query()->create(['name' => 'Cliente', 'email' => 'onepage-customer@example.com', 'password' => Hash::make('password'), 'email_verified_at' => now()]);
        $user->assignRole('customer');
        $address = CustomerAddress::query()->create(['address' => 'Via Test 1', 'city' => 'Città', 'province' => 'XX', 'zip_code' => '00000', 'country' => 'Italia']);
        Customer::query()->create(['user_id' => $user->id, 'customer_type' => 'Azienda', 'email' => $user->email, 'name' => 'C', 'surname' => 'T', 'billing_address_id' => $address->id, 'shipping_address_id' => $address->id]);
        session(['cart' => $this->sessionCart]);
        Livewire::actingAs($user)->test(FrontendCheckoutOnepage::class)
            ->assertSet('step', 2)->assertSet('billing.bill_city', 'Città')
            ->call('goTo', 1)->assertSet('step', 2, 'a logged customer cannot reopen the method step');

        $admin = User::query()->create(['name' => 'Admin', 'email' => 'onepage-admin@example.com', 'password' => Hash::make('password')]);
        $admin->assignRole('admin');
        $this->actingAs($admin)->withSession(['cart' => $this->sessionCart])->get('/checkout')->assertRedirect(route('frontend.cart.index'));
    }
}
