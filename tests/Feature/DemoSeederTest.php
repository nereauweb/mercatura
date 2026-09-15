<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\Demo\DemoCustomersSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\Support\LegacyContent;
use Tests\TestCase;

/**
 * docs/ARCHITECTURE.md §9 Phase 6 stop criterion: a fresh database plus
 * `migrate --seed` gives a browsable, neutral shop.
 */
class DemoSeederTest extends TestCase
{
    use DatabaseTransactions;

    public function test_seed_gives_a_browsable_neutral_shop(): void
    {
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(24, Product::query()->where('sku', 'like', 'DEMO-%')->count());
        $this->assertTrue(Product::query()->where('active', 1)->where('variants_min_price', '>', 0)->count() === 24, 'every product has a main variant with prices');

        $home = $this->get('/')->assertOk();
        $home->assertSee('Cappellino 5 pannelli')->assertSee('Shopper in cotone')->assertSee('demo-abbigliamento.png');
        $this->assertDoesNotMatchRegularExpression(LegacyContent::pattern(), (string) $home->getContent(), 'demo home carries legacy content');

        $this->get('/categorie/t-shirt')->assertOk()->assertSee('T-shirt classica in cotone')->assertSee('T-shirt in cotone organico');
        $this->get('/prodotti')->assertOk()->assertSee('Basic Line');

        $product = Product::query()->where('sku', 'DEMO-010')->firstOrFail();
        $this->get('/prodotti/'.$product->slug())->assertOk()
            ->assertSee('Borraccia termica 500 ml')
            ->assertSee('productConfigurator', false)
            ->assertSee('"@type":"Product"', false)
            ->assertSee('Incisione laser');

        $this->get('/contenuti/novita')->assertOk();
        $this->get('/contenuti/gadget-green')->assertOk()->assertSee('Shopper in cotone');
        $this->get('/contenuti/chi-siamo')->assertOk()->assertSee('demo-chi-siamo.jpg');
        $this->get('/contenuti/'.config('mercatura.legal_pages.privacy'))->assertOk();
        $this->get('/blog')->assertOk()->assertSee('Come scegliere la tecnica di stampa');
        $this->get('/blog/come-scegliere-la-tecnica-di-stampa')->assertOk();

        $suggest = $this->getJson('/search/suggest/borraccia')->assertOk()->json();
        $this->assertCount(2, $suggest);

        $customer = User::query()->where('email', DemoCustomersSeeder::CUSTOMERS[0][0])->firstOrFail();
        $this->assertTrue($customer->hasRole('customer'));
        $order = Order::query()->where('user_id', $customer->id)->where('payment_method', 'bank_transfer')->firstOrFail();
        $this->assertGreaterThan(0, (float) $order->total_taxed_price);
        $this->actingAs($customer)->get('/ordini')->assertOk();
        $this->actingAs($customer)->get('/ordine/'.$order->id)->assertOk()->assertSee(OrderItem::query()->where('order_id', $order->id)->firstOrFail()->product_name);

        $this->assertTrue(User::query()->where('email', DemoCustomersSeeder::ADMIN_EMAIL)->firstOrFail()->hasRole('admin'));
    }

    public function test_seeding_twice_is_a_no_op(): void
    {
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
        $products = Product::query()->count();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($products, Product::query()->count());
    }
}
