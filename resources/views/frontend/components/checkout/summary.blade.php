{{-- @mercatura-view frontend.components.checkout.summary @version 1 --}}
{{-- Cart totals box; on the cart page it also carries the "complete order" form. Stack: cart-summary-after. --}}
@props(['cart', 'isCart' => false])
@php $brand = config('brand'); $money = fn ($v) => number_format((float) $v, 2, ',', '.').'&nbsp;€'; @endphp
<aside {{ $attributes->merge(['class' => 'overflow-hidden rounded-card border border-border-muted bg-surface text-sm']) }}>
    @if($cart['items_price'] > 0)
    <h2 class="px-3 py-2 font-bold uppercase text-primary">{{ __('frontend.cart.total') }}</h2>
    <table class="w-full">
        <tbody>
            <tr class="border-t border-border-muted bg-surface-muted"><th scope="row" class="px-3 py-1 text-left font-medium">{{ __('frontend.cart.items_total') }}</th><td class="px-3 py-1 text-right font-bold">{!! $money($cart['items_price']) !!}</td></tr>
            <tr class="border-t border-border-muted bg-surface-muted"><th scope="row" class="px-3 py-1 text-left font-medium">{{ __('frontend.cart.delivery') }}</th><td class="px-3 py-1 text-right font-bold">{!! $cart['delivery_cost'] == 0 ? __('frontend.cart.free') : $money($cart['delivery_cost']) !!}</td></tr>
            <tr class="border-t border-border-muted bg-surface-muted"><th scope="row" class="px-3 py-1 text-left font-medium">{{ __('frontend.cart.net_total') }}</th><td class="px-3 py-1 text-right font-bold">{!! $money($cart['total_price']) !!}</td></tr>
            <tr class="border-t border-border-muted bg-surface-muted"><th scope="row" class="px-3 py-1 text-left font-medium">{{ __('frontend.cart.vat_total') }}</th><td class="px-3 py-1 text-right font-bold">{!! $money($cart['tax']) !!}</td></tr>
            @if($cart['total_additional_costs'] > 0)
            <tr class="border-t border-border-muted bg-surface-muted"><th scope="row" class="px-3 py-1 text-left font-medium">{{ __('frontend.cart.additional_costs_total') }}</th><td class="px-3 py-1 text-right font-bold">{!! $money($cart['total_additional_costs']) !!}</td></tr>
            @endif
            <tr class="border-t border-border bg-primary-soft"><th scope="row" class="px-3 py-2 text-left font-bold text-primary">{{ __('frontend.cart.cart_total') }}</th><td class="px-3 py-2 text-right text-lg font-bold text-primary">{!! $money($cart['total_taxed_price']) !!}</td></tr>
        </tbody>
    </table>
    @endif
    <div class="space-y-4 p-3">
        @if($cart['items_price'] > 0)
            @if($isCart)
            <form method="POST" action="{{ route('frontend.cart.checkout') }}" class="text-center">
                @csrf
                <button type="submit" class="w-full rounded-card bg-accent px-4 py-3 font-bold uppercase text-on-accent hover:bg-accent-strong">{{ __('frontend.cart.complete_order') }}</button>
            </form>
            @endif
        @else
            <a href="{{ route('frontend.home') }}" class="block rounded-card bg-accent px-4 py-3 text-center font-bold uppercase text-on-accent hover:bg-accent-strong">{{ __('frontend.cart.start_shopping') }}</a>
        @endif
        @stack('cart-summary-after')
        @if($brand['contact']['phone'])
        <p class="flex items-start gap-2 text-xs text-text-muted"><x-frontend::icon name="phone" class="mt-0.5 h-4 w-4 shrink-0 text-primary" /><span>{{ __('frontend.cart.delivery_help', ['phone' => '']) }} <a href="tel:{{ preg_replace('/[^\d+]/', '', $brand['contact']['phone']) }}" class="text-accent">{{ $brand['contact']['phone'] }}</a></span></p>
        @endif
        <div>
            <h3 class="text-xs font-bold uppercase text-primary">{{ __('frontend.cart.secure_payments') }}</h3>
            <ul class="mt-1 flex items-center gap-3">
                <li><img src="/img/logo_paypal.png" alt="PayPal" width="120" height="30" loading="lazy" class="h-6 w-auto"></li>
                <li><img src="/img/logo_visa.png" alt="Visa" width="80" height="26" loading="lazy" class="h-6 w-auto"></li>
                <li><img src="/img/logo_mastercard.png" alt="Mastercard" width="80" height="50" loading="lazy" class="h-6 w-auto"></li>
            </ul>
        </div>
    </div>
</aside>
