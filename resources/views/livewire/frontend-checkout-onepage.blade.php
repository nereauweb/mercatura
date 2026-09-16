{{-- @mercatura-view livewire.frontend-checkout-onepage @version 1 --}}
{{-- Onepage checkout body: six accordion steps on the left, progress and totals on the right.
     Forms send their fields as one array to the component (FormData), after a captcha refresh where a field exists. --}}
@php
    $o = 'frontend.onepage.';
    $formConfig = [
        'type' => $billing['customer_type'] ?? '',
        'privateType' => \App\Models\Customer::TYPE_PRIVATE,
        'publicAdminType' => \App\Models\Customer::TYPE_PUBLIC_ADMIN,
        'companyType' => 'Azienda',
        'messages' => ['tax_code' => __('frontend.forms.tax_code_error'), 'vat_code' => __('frontend.forms.vat_code_error'), 'ipa_code' => __('frontend.forms.ipa_code_error'), 'cig_code' => __('frontend.forms.cig_code_error'), 'password_mismatch' => __('frontend.forms.password_mismatch')],
    ];
    $billingAddress = ['address' => $billing['bill_address'] ?? '', 'city' => $billing['bill_city'] ?? '', 'province' => $billing['bill_province'] ?? '', 'zip_code' => $billing['bill_zip_code'] ?? '', 'country' => $billing['bill_country'] ?? '', 'notes' => $billing['bill_notes'] ?? ''];
    $shippingAddress = ['address' => $shipping['shipping_address'] ?? '', 'city' => $shipping['shipping_city'] ?? '', 'province' => $shipping['shipping_province'] ?? '', 'zip_code' => $shipping['shipping_zip_code'] ?? '', 'country' => $shipping['shipping_country'] ?? '', 'notes' => $shipping['shipping_notes'] ?? ''];
    $methods = $paymentMethods;
    $logos = ['stripe' => [['/img/logo_visa.png', 'Visa', 80, 26], ['/img/logo_mastercard.png', 'Mastercard', 80, 50]], 'paypal' => [['/img/logo_paypal.png', 'PayPal', 120, 30]]];
    $money = fn ($v) => number_format((float) $v, 2, ',', '.').'&nbsp;€';
    $summaries = [
        1 => $customer ? __($o.'summary_customer', ['name' => trim(($billing['name'] ?? '').' '.($billing['surname'] ?? '')), 'email' => $billing['email'] ?? '']) : null,
        2 => ! empty($billing['bill_city']) ? __($o.'summary_address', ['address' => $billing['bill_address'] ?? '', 'zip' => $billing['bill_zip_code'] ?? '', 'city' => $billing['bill_city'] ?? '', 'province' => $billing['bill_province'] ?? '']) : null,
        3 => ! empty($shipping['shipping_city']) ? __($o.'summary_address', ['address' => $shipping['shipping_address'] ?? '', 'zip' => $shipping['shipping_zip_code'] ?? '', 'city' => $shipping['shipping_city'] ?? '', 'province' => $shipping['shipping_province'] ?? '']) : null,
        4 => $reached > 4 ? __($o.'courier').' — '.($cart && $cart['delivery_cost'] == 0 ? __($o.'free') : strip_tags(str_replace('&nbsp;', ' ', $money($cart['delivery_cost'] ?? 0)))) : null,
        5 => $paymentMethod !== '' ? __('frontend.checkout.payment_'.$paymentMethod) : null,
    ];
@endphp
<div class="grid gap-6 md:grid-cols-3" x-data="{
        async submitWith(form, method, field, action) {
            const driver = window.mercaturaCaptcha;
            if (driver && typeof driver.refresh === 'function' && field && document.getElementById(field)) { try { await driver.refresh(field, action); } catch (e) {} }
            $wire[method](Object.fromEntries(new FormData(form)));
        }
    }">
    <div class="space-y-3 md:col-span-2">
        <x-frontend::forms.errors />
        @if($cart === null)
            <p class="rounded-card bg-surface-muted p-6 text-center font-semibold">{{ __('frontend.cart.empty') }}</p>
        @else

        {{-- 1 · checkout method --}}
        <x-frontend::checkout.onepage-step :number="1" :title="__($o.'steps.method')" :current="$step" :reached="$reached" :editable="! auth()->check()">
            <div class="grid gap-6 md:grid-cols-2">
                <form @submit.prevent="submitWith($el, 'login', 'onepage_login_captcha', 'login')" class="space-y-3 rounded-card bg-surface-muted p-4">
                    <h3 class="font-bold text-primary">{{ __($o.'login_title') }}</h3>
                    <p class="text-xs text-text-muted">{{ __($o.'login_hint') }}</p>
                    <x-frontend::forms.input name="email" id="onepage-login-email" type="email" :label="__('frontend.forms.email')" required autocomplete="email" />
                    <x-frontend::forms.input name="password" id="onepage-login-password" type="password" :label="__('frontend.forms.password')" required autocomplete="current-password" />
                    @error('auth')<p class="text-xs text-danger" data-field-error="auth">{{ $message }}</p>@enderror
                    <x-frontend::captcha field="onepage_login_captcha" action="login" />
                    <button type="submit" wire:loading.attr="disabled" class="w-full rounded-card bg-primary px-4 py-2 font-bold uppercase text-on-primary hover:bg-primary-strong disabled:opacity-50">{{ __('frontend.checkout.login') }}</button>
                    <p class="text-xs"><a href="{{ route('frontend.password_reset.request') }}" class="text-accent underline">{{ __('frontend.checkout.forgot_password') }}</a></p>
                </form>
                <form x-data="customerForm(@js($formConfig))" @submit.prevent="beforeSubmit($event); if (passwordsMatch() && $el.checkValidity()) submitWith($el, 'register', 'onepage_register_captcha', 'register')" class="space-y-3 rounded-card bg-surface-muted p-4">
                    <h3 class="font-bold text-primary">{{ __($o.'register_title') }}</h3>
                    <p class="text-xs text-text-muted">{{ __($o.'register_hint') }}</p>
                    <x-frontend::forms.customer-fields :provinces="$provinces" :with-password="true" :headings="false" />
                    <x-frontend::forms.consents :newsletter="true" />
                    <x-frontend::captcha field="onepage_register_captcha" action="register" />
                    <p class="text-xs text-text-muted">{{ __('frontend.forms.required_hint') }}</p>
                    <button type="submit" wire:loading.attr="disabled" class="w-full rounded-card bg-accent px-4 py-2 font-bold uppercase text-on-accent hover:bg-accent-strong disabled:opacity-50">{{ __('frontend.checkout.register') }}</button>
                </form>
            </div>
        </x-frontend::checkout.onepage-step>

        {{-- 2 · billing --}}
        <x-frontend::checkout.onepage-step :number="2" :title="__($o.'steps.billing')" :current="$step" :reached="$reached">
            <form x-data="customerForm(@js($formConfig))" @submit.prevent="beforeSubmit($event); if ($el.checkValidity()) submitWith($el, 'saveBilling', null, null)" class="space-y-4">
                <x-frontend::forms.customer-fields :customer="$billing" :billing="$billingAddress" :provinces="$provinces" :with-notes="true" :with-shipping="false" />
                <x-frontend::forms.checkbox name="ship_to_billing" :checked="$shipToBilling">{{ __($o.'ship_here') }}</x-frontend::forms.checkbox>
                <p class="text-xs text-text-muted">{{ __('frontend.forms.required_hint') }}</p>
                <button type="submit" wire:loading.attr="disabled" class="rounded-card bg-accent px-6 py-2 font-bold uppercase text-on-accent hover:bg-accent-strong disabled:opacity-50">{{ __($o.'continue') }}</button>
            </form>
        </x-frontend::checkout.onepage-step>

        {{-- 3 · shipping --}}
        <x-frontend::checkout.onepage-step :number="3" :title="__($o.'steps.shipping')" :current="$step" :reached="$reached">
            <form @submit.prevent="if ($el.checkValidity()) submitWith($el, 'saveShipping', null, null); else $el.reportValidity()" class="space-y-4">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <x-frontend::forms.input name="shipping_address" :label="__('frontend.forms.address')" :value="$shippingAddress['address']" required autocomplete="shipping street-address" />
                    <x-frontend::forms.input name="shipping_city" :label="__('frontend.forms.city')" :value="$shippingAddress['city']" required autocomplete="shipping address-level2" />
                    <x-frontend::forms.select name="shipping_province" :label="__('frontend.forms.province')" :options="$provinces" :value="$shippingAddress['province']" required :placeholder="__('frontend.forms.select')" />
                    <x-frontend::forms.input name="shipping_zip_code" :label="__('frontend.forms.zip_code')" :value="$shippingAddress['zip_code']" required autocomplete="shipping postal-code" />
                    <x-frontend::forms.input name="shipping_country" :label="__('frontend.forms.country')" :value="$shippingAddress['country']" required autocomplete="shipping country-name" />
                    <x-frontend::forms.input name="shipping_notes" :label="__('frontend.forms.notes')" :value="$shippingAddress['notes']" />
                </div>
                <button type="submit" wire:loading.attr="disabled" class="rounded-card bg-accent px-6 py-2 font-bold uppercase text-on-accent hover:bg-accent-strong disabled:opacity-50">{{ __($o.'continue') }}</button>
            </form>
        </x-frontend::checkout.onepage-step>

        {{-- 4 · shipping method --}}
        <x-frontend::checkout.onepage-step :number="4" :title="__($o.'steps.shipping_method')" :current="$step" :reached="$reached">
            <label class="flex cursor-pointer items-center gap-3 rounded-card border border-border-muted bg-surface p-3 text-sm"><input type="radio" name="shipping_method" value="courier" checked class="accent-accent"><span class="font-semibold">{{ __($o.'courier') }}</span><span class="ml-auto font-bold">{!! $cart['delivery_cost'] == 0 ? __($o.'free') : $money($cart['delivery_cost']) !!}</span></label>
            @if($cart['delivery_days'])<p class="mt-2 text-xs text-text-muted">{{ __('frontend.cart.delivery_help', ['phone' => config('brand.contact.phone')]) }}</p>@endif
            <button type="button" wire:click="saveShippingMethod" wire:loading.attr="disabled" class="mt-4 rounded-card bg-accent px-6 py-2 font-bold uppercase text-on-accent hover:bg-accent-strong disabled:opacity-50">{{ __($o.'continue') }}</button>
        </x-frontend::checkout.onepage-step>

        {{-- 5 · payment --}}
        <x-frontend::checkout.onepage-step :number="5" :title="__($o.'steps.payment')" :current="$step" :reached="$reached">
            <ul class="space-y-2">
                @foreach($methods as $method)
                <li><label class="flex cursor-pointer items-center gap-3 rounded-card border border-border-muted bg-surface p-3 text-sm"><input type="radio" wire:model="paymentMethod" value="{{ $method }}" class="accent-accent"><span class="font-semibold">{{ __('frontend.checkout.payment_'.$method) }}</span>
                    @foreach($logos[$method] ?? [] as [$src, $alt, $w, $h])<img src="{{ $src }}" alt="{{ $alt }}" width="{{ $w }}" height="{{ $h }}" loading="lazy" class="ml-auto h-6 w-auto">@endforeach
                </label></li>
                @endforeach
            </ul>
            @error('paymentMethod')<p class="mt-1 text-xs text-danger" data-field-error="paymentMethod">{{ $message }}</p>@enderror
            <button type="button" wire:click="savePayment" wire:loading.attr="disabled" class="mt-4 rounded-card bg-accent px-6 py-2 font-bold uppercase text-on-accent hover:bg-accent-strong disabled:opacity-50">{{ __($o.'continue') }}</button>
        </x-frontend::checkout.onepage-step>

        {{-- 6 · review --}}
        <x-frontend::checkout.onepage-step :number="6" :title="__($o.'steps.review')" :current="$step" :reached="$reached">
            <x-frontend::cart.table :cart="$cart" :editable="false" class="bg-surface p-0" />
            <form @submit.prevent="submitWith($el, 'placeOrder', null, null)" class="mt-4 space-y-4">
                <section>
                    <h3 class="font-bold text-primary">{{ __('frontend.checkout.notices_title') }}</h3>
                    <ul class="mt-1 list-inside list-disc space-y-1 text-xs text-text-muted"><li>{{ __('frontend.checkout.notice_bank') }}</li><li>{{ __('frontend.checkout.notice_printed') }}</li></ul>
                </section>
                <x-frontend::forms.consents />
                @error('checkout')<p class="text-xs text-danger" data-field-error="checkout">{{ $message }}</p>@enderror
                <button type="submit" wire:loading.attr="disabled" class="rounded-card bg-accent px-8 py-3 font-bold uppercase text-on-accent hover:bg-accent-strong disabled:opacity-50"><span wire:loading.remove wire:target="placeOrder">{{ __($o.'place_order') }}</span><span wire:loading wire:target="placeOrder">{{ __($o.'working') }}</span></button>
            </form>
        </x-frontend::checkout.onepage-step>
        @endif
    </div>

    <aside class="space-y-4 md:col-span-1">
        <div class="rounded-card border border-border-muted bg-surface text-sm">
            <h2 class="px-3 py-2 font-bold uppercase text-primary">{{ __($o.'your_checkout') }}</h2>
            <ol class="divide-y divide-border-muted">
                @foreach(\App\Http\Livewire\FrontendCheckoutOnepage::STEPS as $index => $key)
                    @php $n = $index + 1; @endphp
                    <li class="flex items-start gap-2 px-3 py-2 {{ $n === $step ? 'font-semibold text-primary' : ($n < $step ? 'text-text' : 'text-text-muted') }}">
                        <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-current text-xs">{{ $n }}</span>
                        <span class="min-w-0"><span class="block">{{ __($o.'steps.'.$key) }}</span>@if($n < $step && ! empty($summaries[$n]))<span class="block truncate text-xs font-normal text-text-muted">{{ $summaries[$n] }}</span>@endif</span>
                    </li>
                @endforeach
            </ol>
        </div>
        @if($cart !== null)
            <x-frontend::checkout.summary :cart="$cart" />
        @endif
    </aside>
</div>
