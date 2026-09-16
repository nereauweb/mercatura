{{-- @mercatura-view frontend.components.quick-quote @version 1 --}}
{{-- Quick-quote modal (docs/04_STOREFRONT_FLOWS.md §4.4), mounted once by the layout when
     mercatura.storefront.quick_quote = modal. Opens on the `quick-quote-open` window event
     ({ articleId?, quantity? }); the contact fields and the products share the session store
     of the quotation page, which remains the no-JS fallback. --}}
@php
    // Mounted by the layout on every page, including ones rendered outside the web middleware: the form components expect $errors.
    if (! isset($errors)) { view()->share('errors', new \Illuminate\Support\ViewErrorBag); $errors = view()->shared('errors'); }
    $q = 'frontend.quick_quote.';
    $qCust = (array) session('quotation.customer', []);
    $config = [
        'csrf' => csrf_token(),
        'endpoints' => [
            'list' => route('frontend.quotation.api.list'),
            'add' => route('frontend.quotation.api.add'),
            'update' => route('frontend.quotation.api.update', ['id' => '__ID__']),
            'remove' => route('frontend.quotation.api.remove', ['id' => '__ID__']),
            'send' => route('frontend.quotation.api.send'),
        ],
        'customer' => $qCust,
        'count' => is_array(session('quotation.products')) ? count(session('quotation.products')) : 0,
        'captchaField' => 'quick_quote_captcha',
        'captchaAction' => 'request_quotation',
        'labels' => ['error' => __($q.'error')],
        'conversion' => config('gtm.google_ads_id') && config('gtm.conversions.quotation_sent') ? config('gtm.google_ads_id').'/'.config('gtm.conversions.quotation_sent') : null,
    ];
    $types = \App\Models\Customer::CUSTOMER_TYPES;
    $activities = \App\Models\Customer::$activities;
    $brand = config('brand');
@endphp
<div x-data="quickQuote(@js($config))" @quick-quote-open.window="openWith($event.detail || {})" @keydown.escape.window="close()">
    <div x-cloak x-show="open" x-transition.opacity class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/60 p-4 md:p-8">
        <div id="quick-quote" role="dialog" aria-modal="true" aria-label="{{ __($q.'title') }}" @click.outside="close()" class="w-full max-w-5xl rounded-card bg-surface shadow-xl">
            <header class="flex items-center justify-between border-b border-border px-4 py-3">
                <h2 class="text-lg font-bold uppercase text-primary">{{ __($q.'title') }}</h2>
                <button type="button" @click="close()" class="rounded p-1 text-text-muted hover:text-danger" aria-label="{{ __($q.'close') }}"><x-frontend::icon name="close" /></button>
            </header>

            <div x-cloak x-show="sent" class="p-6 text-center">
                <p class="text-xl font-bold text-primary">{{ __('frontend.quotation.sent_title') }}</p>
                <p class="mt-2 font-semibold">{{ __('frontend.quotation.sent_text') }}</p>
                @if($brand['contact']['phone'] || $brand['contact']['email'])
                <p class="mt-3 text-sm text-text-muted">{{ __('frontend.quotation.sent_help', ['phone' => $brand['contact']['phone'], 'email' => $brand['contact']['email']]) }}</p>
                @endif
                <button type="button" @click="close()" class="mt-6 rounded-full bg-accent px-6 py-2 text-sm font-bold uppercase text-on-accent hover:bg-accent-strong">{{ __($q.'sent_close') }}</button>
            </div>

            <form x-show="! sent" x-ref="form" @submit.prevent="send()" class="grid gap-6 p-4 md:grid-cols-5 md:p-6" novalidate>
                {{-- left · contact fields, the same as the quotation page --}}
                <section class="md:col-span-2">
                    <h3 class="font-bold text-primary">{{ __('frontend.quotation.email_title') }}</h3>
                    <p class="text-xs text-text-muted">{{ __('frontend.quotation.required_hint') }}</p>
                    <div class="mt-3 grid gap-3">
                        <x-frontend::forms.input name="customer[name]" :label="__('frontend.forms.name')" x-model="customer.name" autocomplete="given-name" />
                        <x-frontend::forms.input name="customer[surname]" :label="__('frontend.forms.surname')" x-model="customer.surname" autocomplete="family-name" />
                        <x-frontend::forms.input name="customer[email]" type="email" :label="__('frontend.forms.email')" x-model="customer.email" required autocomplete="email" />
                        <x-frontend::forms.input name="customer[company]" :label="__('frontend.forms.company')" x-model="customer.company" autocomplete="organization" />
                        <x-frontend::forms.select name="customer[customer_type]" :label="__('frontend.forms.customer_type')" :options="$types" x-model="customer.customer_type" :placeholder="__('frontend.forms.customer_type')" />
                        <x-frontend::forms.select name="customer[activity]" :label="__('frontend.forms.activity')" :options="$activities" x-model="customer.activity" :placeholder="__('frontend.forms.activity')" />
                        <x-frontend::forms.input name="customer[phone]" type="tel" :label="__('frontend.forms.phone')" x-model="customer.phone" autocomplete="tel" />
                        <x-frontend::forms.consents :terms="false" :newsletter="true" :gdpr-checked="(bool) session('quotation.consent_gdpr', false)" :newsletter-checked="(bool) session('quotation.subscribe_newsletter', false)" />
                        <x-frontend::captcha field="quick_quote_captcha" action="request_quotation" />
                    </div>
                </section>

                {{-- right · products to quote --}}
                <section class="md:col-span-3">
                    <div class="flex items-baseline justify-between gap-2">
                        <h3 class="font-bold text-primary">{{ __($q.'products') }}</h3>
                        <span x-cloak x-show="loading" class="text-xs text-text-muted">{{ __($q.'loading') }}</span>
                    </div>
                    <p x-cloak x-show="products.length === 0" class="mt-3 rounded-card bg-primary-soft p-4 text-sm">{{ __($q.'empty') }}</p>
                    <ul class="mt-3 space-y-3">
                        <template x-for="product in products" :key="product.id">
                            <li class="flex flex-wrap gap-3 rounded-card border border-border-muted p-3">
                                <img :src="product.image" alt="" width="80" height="80" loading="lazy" class="h-20 w-20 object-contain" x-show="product.image">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="font-bold" x-text="product.name"></p>
                                        <button type="button" @click="remove(product.id)" class="rounded p-1 text-text-muted hover:text-danger" :aria-label="@js(__($q.'remove'))"><x-frontend::icon name="close" class="h-4 w-4" /></button>
                                    </div>
                                    <p class="text-xs text-text-muted">{{ __('frontend.product.code') }} <strong x-text="product.sku"></strong></p>
                                    <div class="mt-2 grid gap-2 sm:grid-cols-3">
                                        <label class="block text-xs"><span class="mb-1 block font-semibold uppercase">{{ __('frontend.quotation.color') }}</span>
                                            <select x-model="product.color" @change="update(product)" :disabled="product.colors.length === 0" class="w-full rounded border border-border bg-surface px-2 py-1.5 text-sm">
                                                <template x-if="product.colors.length === 0"><option :value="product.color" x-text="product.color"></option></template>
                                                <template x-for="color in product.colors" :key="color.label"><option :value="color.label" x-text="color.label"></option></template>
                                            </select></label>
                                        <label class="block text-xs"><span class="mb-1 block font-semibold uppercase">{{ __('frontend.quotation.size') }}</span>
                                            <select x-model="product.size" @change="update(product)" :disabled="product.sizes.length < 2" class="w-full rounded border border-border bg-surface px-2 py-1.5 text-sm">
                                                <template x-if="product.sizes.length === 0"><option :value="product.size" x-text="product.size"></option></template>
                                                <template x-for="size in product.sizes" :key="size"><option :value="size" x-text="size"></option></template>
                                            </select></label>
                                        <label class="block text-xs"><span class="mb-1 block font-semibold uppercase">{{ __('frontend.quotation.quantity') }}</span>
                                            <input type="number" min="1" step="1" x-model="product.quantity" @change="update(product)" :class="parseInt(product.quantity, 10) > 0 ? 'border-border' : 'border-danger'" class="w-full rounded border bg-surface px-2 py-1.5 text-sm"></label>
                                    </div>
                                    <fieldset class="mt-2 text-xs">
                                        <legend class="mb-1 font-semibold uppercase">{{ __('frontend.quotation.printing_question') }}</legend>
                                        <label class="mr-4 inline-flex items-center gap-1"><input type="radio" :name="'printing-' + product.id" value="{{ __('frontend.quotation.yes') }}" x-model="product.printing" @change="update(product)" class="accent-accent">{{ __('frontend.quotation.yes') }}</label>
                                        <label class="inline-flex items-center gap-1"><input type="radio" :name="'printing-' + product.id" value="{{ __('frontend.quotation.no') }}" x-model="product.printing" @change="update(product)" class="accent-accent">{{ __('frontend.quotation.no') }}</label>
                                    </fieldset>
                                    <label class="mt-2 block text-xs"><span class="mb-1 block font-semibold uppercase">{{ __('frontend.quotation.notes') }}</span>
                                        <textarea rows="2" x-model="product.notes" @change="update(product)" placeholder="{{ __('frontend.quotation.notes_placeholder') }}" class="w-full rounded border border-border bg-surface px-2 py-1.5 text-sm"></textarea></label>
                                </div>
                            </li>
                        </template>
                    </ul>

                    <div x-cloak x-show="errors.length" class="mt-4 rounded-card border border-danger bg-danger-soft p-3 text-sm text-danger" role="alert">
                        <ul class="list-inside list-disc"><template x-for="error in errors" :key="error"><li x-text="error"></li></template></ul>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center justify-end gap-3">
                        <a href="{{ route('frontend.home') }}" @click="close()" class="rounded-full border border-accent px-5 py-2 text-sm font-bold uppercase text-accent hover:bg-accent hover:text-on-accent">{{ __('frontend.quotation.add_another') }}</a>
                        <button type="submit" :disabled="loading || products.length === 0" class="rounded-full bg-accent px-6 py-2 text-sm font-bold uppercase text-on-accent hover:bg-accent-strong disabled:opacity-50"><span x-text="loading ? @js(__($q.'sending')) : @js(__('frontend.quotation.send'))">{{ __('frontend.quotation.send') }}</span></button>
                    </div>
                </section>
            </form>
        </div>
    </div>
    <x-frontend::captcha-init />
</div>
