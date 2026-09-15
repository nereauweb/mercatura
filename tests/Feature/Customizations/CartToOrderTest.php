<?php

declare(strict_types=1);

namespace Tests\Feature\Customizations;

use App\Http\Controllers\FrontendCartController;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemArticle;
use App\Models\OrderItemPrinting;
use App\Models\User;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CustomizationFixture;
use Tests\TestCase;

/** What a configured cart line becomes once the order is stored (docs/03_CUSTOMIZATIONS.md §4.5). */
final class CartToOrderTest extends TestCase
{
    use DatabaseTransactions;

    public function test_the_order_keeps_totals_articles_and_print_labels(): void
    {
        $this->seed(CoreSeeder::class);
        $f = CustomizationFixture::create();
        $user = User::query()->create(['name' => 'Cliente', 'email' => 'cliente-fixture@example.com', 'password' => Hash::make('password'), 'email_verified_at' => now()]);
        $user->assignRole('customer');
        $address = CustomerAddress::query()->create(['address' => 'Via Test 1', 'city' => 'Città', 'province' => 'XX', 'zip_code' => '00000', 'country' => 'Italia']);
        Customer::query()->create([
            'user_id' => $user->id, 'customer_type' => 'Azienda', 'email' => $user->email, 'phone' => '+39 000 000 0000', 'name' => 'Cliente', 'surname' => 'Test',
            'company' => 'Test S.r.l.', 'billing_address_id' => $address->id, 'shipping_address_id' => $address->id,
        ]);
        config(['mercatura.checkout.payment_methods' => ['bank_transfer']]);

        $line = ['articles' => [[$f->a->id, 60], [$f->b->id, 40]], 'printings' => [$f->screenOneColorA->id], 'has_packaging' => 1];
        $this->actingAs($user)->withSession(['cart' => ['line1' => $line]])
            ->post(action([FrontendCartController::class, 'store_order']), ['payment_method' => 'bank_transfer', 'consent_gdpr' => '1', 'consent_terms' => '1'])
            ->assertOk();

        $order = Order::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('requested', $order->status);
        $this->assertEqualsWithDelta(1218.00, (float) $order->items_price, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $order->delivery_cost, 0.001);
        $this->assertEqualsWithDelta(1218.00, (float) $order->total_price, 0.001);
        $this->assertEqualsWithDelta(267.96, (float) $order->total_tax, 0.001);
        $this->assertEqualsWithDelta(1495.96, (float) $order->total_taxed_price, 0.001);

        $this->assertSame(1, OrderItem::query()->where('order_id', $order->id)->count());
        $item = OrderItem::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(100, (int) $item->quantity);
        $this->assertEqualsWithDelta(1218.00, (float) $item->price, 0.001, 'setup, start and packaging are folded into the item price');
        $this->assertEqualsWithDelta(12.18, (float) $item->unit_price, 0.001, 'stored since v2c.0 (docs/03 defect 10)');

        $articles = OrderItemArticle::query()->where('item_id', $item->id)->orderBy('id')->get();
        $this->assertSame([[$f->a->sku, 60, 10.4, 624.0], [$f->b->sku, 40, 10.4, 416.0]], $articles->map(fn (OrderItemArticle $a) => [$a->article_sku, (int) $a->quantity, (float) $a->unit_price, (float) $a->price])->all());

        $printings = OrderItemPrinting::query()->where('item_id', $item->id)->get();
        $this->assertCount(1, $printings, 'one row per option chosen, not per article');
        $printing = $printings->firstOrFail();
        $this->assertSame($f->screenOneColorA->id, (int) $printing->printing_variant_color_id);
        $this->assertSame('FRONTE - Serigrafia  10x10 1 colore', $printing->printing_label);
        $this->assertNull($printing->print_file);
        $this->assertSame($f->screenOneColorA->id, $printing->printing()->firstOrFail()->id, 'relation fixed in v2c.0 (docs/03 defect 2)');

        $this->assertSame('Personalizzazioni: FRONTE - Serigrafia  10x10 1 colore', $order->mail_export_items()[0]['printings']);
    }
}
