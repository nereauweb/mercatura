<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Http\Controllers\FrontendCartController;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemCustomization;
use App\Models\User;
use App\Support\Customizations\LinePricer;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CustomizationFixture;
use Tests\TestCase;

/** docs/04_STOREFRONT_FLOWS.md §4.2 (artwork in the configurator), §4.3 (quote-only) and §4.6 (sample lines). */
final class SampleAndArtworkTest extends TestCase
{
    use DatabaseTransactions;

    private CustomizationFixture $f;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        $this->f = CustomizationFixture::create();
        $this->user = User::query()->create(['name' => 'Cliente', 'email' => 'flows-customer@example.com', 'password' => Hash::make('password'), 'email_verified_at' => now()]);
        $this->user->assignRole('customer');
        $address = CustomerAddress::query()->create(['address' => 'Via Test 1', 'city' => 'Città', 'province' => 'XX', 'zip_code' => '00000', 'country' => 'Italia']);
        Customer::query()->create(['user_id' => $this->user->id, 'customer_type' => 'Azienda', 'email' => $this->user->email, 'name' => 'C', 'surname' => 'T', 'billing_address_id' => $address->id, 'shipping_address_id' => $address->id]);
        config(['mercatura.checkout.payment_methods' => ['bank_transfer'], 'mercatura.storefront.samples' => true, 'mercatura.storefront.artwork_in_configurator' => true, 'mercatura.storefront.shipping_date' => true]);
    }

    public function test_a_sample_line_is_one_plain_piece_without_surcharge_and_the_order_says_so(): void
    {
        $line = app(LinePricer::class)->price([[$this->f->a->id, 1]], [$this->f->screenOneColorA->id], true, sample: true);
        $this->assertTrue($line->sample);
        $this->assertSame([], $line->customizations);
        $this->assertEqualsWithDelta(0.0, $line->surcharge, 0.001, 'no minimum surcharge on a sample');
        $this->assertEqualsWithDelta(20.0, $line->price, 0.001, '1 × (10 + 100 %)');

        $this->actingAs($this->user)->withSession(['cart' => ['s' => ['articles' => [[$this->f->a->id, 1]], 'customizations' => [$this->f->screenOneColorA->id], 'has_packaging' => 0, 'sample' => 1]]])
            ->post(action([FrontendCartController::class, 'store_order']), ['payment_method' => 'bank_transfer', 'consent_gdpr' => '1', 'consent_terms' => '1'])->assertOk();
        $order = Order::query()->where('user_id', $this->user->id)->firstOrFail();
        $item = OrderItem::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertTrue((bool) $item->is_sample);
        $this->assertNotNull($item->shipping_date);
        $this->assertSame(0, OrderItemCustomization::query()->where('item_id', $item->id)->count());
    }

    public function test_artwork_uploaded_in_the_configurator_follows_the_line_into_the_order(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->actingAs($this->user);
        $upload = $this->postJson(action([FrontendCartController::class, 'uploadArtwork']), ['file' => UploadedFile::fake()->image('logo.png', 1200, 1200), 'option_id' => $this->f->screenOneColorA->id]);
        $upload->assertOk();
        $token = (string) $upload->json('token');
        $this->assertNotSame('', $token);
        $sessionDir = 'cart-artwork/'.session('cart_artwork_key');
        Storage::disk('local')->assertExists($sessionDir.'/'.$token);

        $payload = ['articles' => [[$this->f->a->id, 100]], 'printings' => [$this->f->screenOneColorA->id], 'has_packaging' => 0, 'artwork' => [$this->f->screenOneColorA->id => $token]];
        $this->post(action([FrontendCartController::class, 'add_to_cart']), ['add_to_cart' => json_encode($payload)])->assertRedirect();
        $line = array_values((array) session('cart'))[0];
        $this->assertSame([$this->f->screenOneColorA->id => $token], $line['artwork']);

        $this->post(action([FrontendCartController::class, 'store_order']), ['payment_method' => 'bank_transfer', 'consent_gdpr' => '1', 'consent_terms' => '1'])->assertOk();
        $row = OrderItemCustomization::query()->where('option_id', $this->f->screenOneColorA->id)->firstOrFail();
        $this->assertNotNull($row->file);
        Storage::disk('public')->assertExists((string) $row->file);
        Storage::disk('local')->assertMissing($sessionDir.'/'.$token);
    }

    public function test_the_sample_request_is_offered_where_the_plan_says_when_enabled(): void
    {
        $url = '/prodotti/'.$this->f->product->slug;
        $page = $this->get($url)->assertOk()->getContent();
        $this->assertStringContainsString('sampleRequest(', $page);
        $this->assertStringContainsString('id="sample-request"', $page);
        $this->assertStringContainsString(__('frontend.product.sample_modal.title'), $page);
        $sheet = $this->get($url.'/'.$this->f->a->sku.'/scheda/disponibilita', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->getContent();
        $this->assertStringContainsString('data-sample-request="'.$this->f->a->id.'"', $sheet, 'the Disponibilità tab offers the sample');

        config(['mercatura.storefront.configurator' => 'modal']);
        $modal = $this->get($url)->assertOk()->getContent();
        $this->assertStringContainsString('requestSample()', $modal, 'the modal configurator offers the sample below the minimum');

        \Illuminate\Support\Facades\Cache::flush();
        config(['mercatura.storefront.samples' => false]);
        $page = $this->get($url)->assertOk()->getContent();
        $this->assertStringNotContainsString('sampleRequest(', $page);
        $sheet = $this->get($url.'/'.$this->f->a->sku.'/scheda/disponibilita', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->getContent();
        $this->assertStringNotContainsString('data-sample-request', $sheet);
    }

    public function test_the_summary_prices_a_sample_and_the_admin_shows_the_badge(): void
    {
        $json = $this->postJson(action([\App\Http\Controllers\FrontendProductController::class, 'configuratorSummary']), ['articles' => [[$this->f->a->id, 1]], 'printings' => [], 'has_packaging' => 0, 'sample' => 1])->assertOk()->json();
        $this->assertTrue($json['sample']);
        $this->assertEqualsWithDelta(20.0, $json['price'], 0.001);
        $this->assertEqualsWithDelta(0.0, $json['surcharge'], 0.001);
        $this->assertNotNull($json['shipping_date']);

        $this->actingAs($this->user)->withSession(['cart' => ['s' => ['articles' => [[$this->f->a->id, 1]], 'customizations' => [], 'has_packaging' => 0, 'sample' => 1]]])
            ->post(action([FrontendCartController::class, 'store_order']), ['payment_method' => 'bank_transfer', 'consent_gdpr' => '1', 'consent_terms' => '1'])->assertOk();
        $order = Order::query()->where('user_id', $this->user->id)->firstOrFail();
        $this->user->assignRole('admin');
        \Livewire\Livewire::actingAs($this->user)->test(\App\Filament\Resources\Orders\Pages\ViewOrder::class, ['record' => $order->id])->assertOk()->assertSee(__('admin.order.sample'));
    }

    public function test_quote_only_products_offer_the_quote_only(): void
    {
        $this->f->product->update(['quote_only' => 1]);
        $html = $this->get('/prodotti/'.$this->f->product->slug)->assertOk()->getContent();
        $this->assertStringContainsString(__('frontend.product.quote_only'), $html);
        $this->assertStringNotContainsString('>'.__('frontend.product.buy').'<', $html);
        $this->assertStringNotContainsString(__('frontend.product.configurator.title'), $html, 'no configurator on a quote-only product');
    }
}
