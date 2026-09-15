{{-- @mercatura-view frontend.components.forms.customer-fields @version 1 --}}
{{-- Customer identity, fiscal data, billing and shipping address. Field names match CustomerFormRules.
     Must be placed inside an element with x-data="customerForm(...)" (see forms.customer-form). --}}
@props(['customer' => [], 'billing' => [], 'shipping' => [], 'provinces' => [], 'withPassword' => false, 'withNotes' => false, 'headings' => true])
@php
    $c = fn (string $key) => $customer[$key] ?? '';
    $b = fn (string $key) => $billing[$key] ?? '';
    $s = fn (string $key) => $shipping[$key] ?? '';
    $types = \App\Models\Customer::CUSTOMER_TYPES;
    $activities = \App\Models\Customer::$activities;
@endphp
<div {{ $attributes->merge(['class' => 'space-y-6']) }}>
    <section>
        @if($headings)<h2 class="mb-3 text-lg font-bold text-primary">{{ __('frontend.forms.customer_data') }}</h2>@endif
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <x-frontend::forms.input name="name" :label="__('frontend.forms.name')" :value="$c('name')" required autocomplete="given-name" />
            <x-frontend::forms.input name="surname" :label="__('frontend.forms.surname')" :value="$c('surname')" required autocomplete="family-name" />
            <x-frontend::forms.input name="email" type="email" :label="__('frontend.forms.email')" :value="$c('email')" required autocomplete="email" />
            <x-frontend::forms.input name="phone" type="tel" :label="__('frontend.forms.phone')" :value="$c('phone')" required autocomplete="tel" />
            @if($withPassword)
                <x-frontend::forms.input name="password" type="password" id="register-password" :label="__('frontend.forms.password')" required minlength="8" autocomplete="new-password" x-ref="password" @input="passwordsMatch()" />
                <x-frontend::forms.input name="password_confirmation" type="password" id="register-password-confirmation" :label="__('frontend.forms.password_confirmation')" required minlength="8" autocomplete="new-password" x-ref="password_confirmation" @input="passwordsMatch()" :hint="__('frontend.forms.password_mismatch')" />
            @endif
            <x-frontend::forms.select name="customer_type" id="customer-type" :label="__('frontend.forms.customer_type')" :options="$types" :value="$c('customer_type')" required :placeholder="__('frontend.forms.select')" x-model="type" @change="typeChanged()" />
            <x-frontend::forms.select name="activity" :label="__('frontend.forms.activity')" :options="$activities" :value="$c('activity')" :placeholder="__('frontend.forms.activity_none')" x-cloak x-show="show('activity')" />
        </div>
    </section>

    <section>
        @if($headings)<h2 class="mb-3 text-lg font-bold text-primary">{{ __('frontend.forms.billing_data') }}</h2>@endif
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <x-frontend::forms.input name="company" :label="__('frontend.forms.company')" :value="$c('company')" x-cloak x-show="show('company')" ::required="required('company')" autocomplete="organization" />
            <x-frontend::forms.input name="tax_code" id="tax-code" :label="__('frontend.forms.tax_code')" :value="$c('tax_code')" x-cloak x-show="show('tax_code')" ::required="required('tax_code')" x-ref="tax_code" @input="normalise($event.target, 'tax_code')" :hint="__('frontend.forms.tax_code_error')" maxlength="16" autocapitalize="characters" />
            <x-frontend::forms.input name="vat_code" id="vat-code" :label="__('frontend.forms.vat_code')" :value="$c('vat_code')" x-cloak x-show="show('vat_code')" ::required="required('vat_code')" x-ref="vat_code" @input="normalise($event.target, 'vat_code')" :hint="__('frontend.forms.vat_code_error')" maxlength="11" inputmode="numeric" />
            <x-frontend::forms.input name="pec" type="email" :label="__('frontend.forms.pec')" :value="$c('pec')" x-cloak x-show="show('pec')" ::required="required('pec')" inputmode="email" />
            <x-frontend::forms.input name="sdi_code" id="sdi-code" :label="__('frontend.forms.sdi_code')" :value="$c('sdi_code')" x-cloak x-show="show('sdi_code')" />
            <x-frontend::forms.input name="ipa_code" id="ipa-code" :label="__('frontend.forms.ipa_code')" :value="$c('ipa_code')" x-cloak x-show="show('ipa_code')" ::required="required('ipa_code')" x-ref="ipa_code" @input="normalise($event.target, 'ipa_code')" :hint="__('frontend.forms.ipa_code_error')" maxlength="6" autocapitalize="characters" />
            <x-frontend::forms.input name="cig_code" id="cig-code" :label="__('frontend.forms.cig_code')" :value="$c('cig_code')" x-cloak x-show="show('cig_code')" ::required="required('cig_code')" x-ref="cig_code" @input="normalise($event.target, 'cig_code')" :hint="__('frontend.forms.cig_code_error')" maxlength="10" autocapitalize="characters" />
            <x-frontend::forms.input name="bill_address" :label="__('frontend.forms.address')" :value="$b('address')" required autocomplete="billing street-address" x-ref="bill_address" />
            <x-frontend::forms.input name="bill_city" :label="__('frontend.forms.city')" :value="$b('city')" required autocomplete="billing address-level2" x-ref="bill_city" />
            <x-frontend::forms.select name="bill_province" :label="__('frontend.forms.province')" :options="$provinces" :value="$b('province')" required :placeholder="__('frontend.forms.select')" x-ref="bill_province" />
            <x-frontend::forms.input name="bill_zip_code" :label="__('frontend.forms.zip_code')" :value="$b('zip_code')" required autocomplete="billing postal-code" x-ref="bill_zip_code" />
            <x-frontend::forms.input name="bill_country" :label="__('frontend.forms.country')" :value="$b('country')" required autocomplete="billing country-name" x-ref="bill_country" />
            @if($withNotes)<x-frontend::forms.input name="bill_notes" :label="__('frontend.forms.notes')" :value="$b('notes')" />@endif
        </div>
    </section>

    <section>
        @if($headings)<h2 class="mb-3 flex flex-wrap items-baseline gap-3 text-lg font-bold text-primary">{{ __('frontend.forms.shipping_question') }} <button type="button" @click="copyBillingToShipping()" class="text-sm font-normal text-accent hover:underline">{{ __('frontend.forms.copy_billing') }}</button></h2>@endif
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <x-frontend::forms.input name="shipping_address" :label="__('frontend.forms.address')" :value="$s('address')" autocomplete="shipping street-address" x-ref="shipping_address" />
            <x-frontend::forms.input name="shipping_city" :label="__('frontend.forms.city')" :value="$s('city')" autocomplete="shipping address-level2" x-ref="shipping_city" />
            <x-frontend::forms.select name="shipping_province" :label="__('frontend.forms.province')" :options="$provinces" :value="$s('province')" :placeholder="__('frontend.forms.select')" x-ref="shipping_province" />
            <x-frontend::forms.input name="shipping_zip_code" :label="__('frontend.forms.zip_code')" :value="$s('zip_code')" autocomplete="shipping postal-code" x-ref="shipping_zip_code" />
            <x-frontend::forms.input name="shipping_country" :label="__('frontend.forms.country')" :value="$s('country')" autocomplete="shipping country-name" x-ref="shipping_country" />
            <x-frontend::forms.input name="shipping_notes" :label="__('frontend.forms.notes')" :value="$s('notes')" />
        </div>
    </section>
</div>
