<?php

declare(strict_types=1);

namespace App\Support\Checkout;

use App\Support\Customizations\LinePricer;
use App\Support\Customizations\Pricing;
use App\Support\FrontendDebugLog;
use App\Support\ShippingDate;
use Carbon\Carbon;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The session cart priced: one array per line (product, articles, chosen
 * customizations, the PricedLine, sample flag, artwork tokens, shipping
 * date) plus the totals. Shared by the cart page, both checkout flows and
 * PlaceOrder, so a line costs the same everywhere.
 */
final class CartData
{
    /**
     * @param  array<string, array<string, mixed>>  $sessionCart
     * @return array<string, mixed>
     */
    public static function build(array $sessionCart): array
    {
        FrontendDebugLog::prezzoCarrello('******** CARICAMENTO CARRELLO');
        $cart = [
            'items' => [],
            'items_price' => 0,
            'delivery_cost' => Pricing::deliveryCost(0.0),
            'total_price' => 0,
            'total_taxed_price' => 0,
            'total_additional_costs' => 0,
            'delivery_days' => 4,
            'delivery_date' => Carbon::now()->addDays(4),
        ];
        $pricer = app(LinePricer::class);
        foreach ($sessionCart as $id => $sci) {
            $line = $pricer->price(
                array_map(fn ($article): array => [intval($article[0]), intval($article[1])], (array) ($sci['articles'] ?? [])),
                array_map('intval', (array) ($sci['customizations'] ?? $sci['printings'] ?? [])),
                (bool) ($sci['has_packaging'] ?? false),
                (bool) ($sci['sample'] ?? false),
            );
            $item = [
                'id' => $id,
                'price' => $line->price,
                'quantity' => $line->quantity,
                'additional_costs' => $line->additionalCosts,
                'has_packaging' => $sci['has_packaging'] ?? false,
                'product' => $line->product,
                'articles' => array_map(fn ($article): array => [
                    'article' => $article->variant,
                    'quantity' => $article->quantity,
                    'unit_price' => $article->unitPrice,
                    'quantity_price' => $article->price,
                    'additional_costs' => $article->additionalCosts,
                ], $line->articles),
                'printings' => array_map(fn ($customization) => $customization->option, $line->customizations),
                'unit_price' => $line->unitPrice(),
                'line' => $line,
                'sample' => $line->sample,
                'artwork' => (array) ($sci['artwork'] ?? []),
                'shipping_date' => config('mercatura.storefront.shipping_date') ? ShippingDate::for($line) : null,
            ];
            $deliveryDays = $line->product->processing_days() + $line->processingDays;
            if ($cart['delivery_days'] < $deliveryDays) {
                $cart['delivery_days'] = $deliveryDays;
            }
            $cart['items'][] = $item;
            $cart['items_price'] += $line->price;
            $cart['total_additional_costs'] += $line->additionalCosts;
            FrontendDebugLog::prezzoCarrello('Riga '.$id.': prezzo '.$line->price.' | quantità '.$line->quantity.' | costi addizionali '.$line->additionalCosts);
        }
        $cart['delivery_cost'] = Pricing::deliveryCost((float) $cart['items_price']);
        $cart['delivery_date'] = Carbon::now()->addDays($cart['delivery_days']);
        $cart['total_price'] = round($cart['items_price'] + $cart['delivery_cost'], 2);
        $cart['tax'] = Pricing::vat((float) $cart['total_price']);
        $cart['total_taxed_price'] = $cart['total_price'] + $cart['tax'] + $cart['total_additional_costs'];
        FrontendDebugLog::prezzoCarrello('Totali carrello: spedizione '.$cart['delivery_cost'].' | imponibile '.$cart['total_price'].' | IVA '.$cart['tax'].' | costi addizionali '.$cart['total_additional_costs'].' | totale '.$cart['total_taxed_price'].' | giorni '.$cart['delivery_days']);

        return $cart;
    }

    /** @return array<string, array<string, mixed>> */
    public static function fromSession(Session $session): array
    {
        $cart = $session->get('cart', []);

        return is_array($cart) ? $cart : [];
    }

    /** Artwork directory of a session, keyed by a token that survives the login (docs/04 §4.2). */
    public static function artworkDirectory(Session $session): string
    {
        $key = (string) $session->get('cart_artwork_key', '');
        if ($key === '') {
            $key = Str::random(32);
            $session->put('cart_artwork_key', $key);
        }

        return 'cart-artwork/'.$key;
    }

    /**
     * @param  array<int, string>  $artwork  option id => token
     * @return array<int, string> option id => absolute path of the uploaded file
     */
    public static function artworkFiles(array $artwork, Session $session): array
    {
        $files = [];
        foreach ($artwork as $optionId => $token) {
            $path = self::artworkDirectory($session).'/'.$token;
            if (Storage::disk('local')->exists($path)) {
                $files[(int) $optionId] = Storage::disk('local')->path($path);
            }
        }

        return $files;
    }
}
