<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Blade;
use Tests\Support\CustomizationFixture;
use Tests\TestCase;

/** "Solo su preventivo": no price on the card, no way into the cart. */
final class QuoteOnlyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_quote_only_product_cannot_be_added_to_the_cart(): void
    {
        $f = CustomizationFixture::create();
        $payload = ['add_to_cart' => json_encode(['product_id' => $f->product->id, 'articles' => [[$f->a->id, 10]]])];

        $f->product->forceFill(['quote_only' => true])->save();
        $this->from('/')->post(route('frontend.cart.add'), $payload)->assertRedirect('/')->assertSessionHas('error', __('frontend.cart.quote_only'));
        $this->assertEmpty(session('cart', []));

        $f->product->forceFill(['quote_only' => false])->save();
        $this->post(route('frontend.cart.add'), $payload)->assertRedirect(route('frontend.cart.index'));
        $this->assertCount(1, session('cart'));
    }

    public function test_the_card_of_a_quote_only_product_shows_no_price(): void
    {
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        $f = CustomizationFixture::create();
        $f->product->forceFill(['quote_only' => true])->save();

        $html = Blade::render('<x-frontend::product.card :product="$product" />', ['product' => $f->product->fresh()]);

        $this->assertStringContainsString(__('frontend.product.card.quote_only'), $html);
        $this->assertStringNotContainsString(__('frontend.product.card.from_price'), $html);
    }
}
