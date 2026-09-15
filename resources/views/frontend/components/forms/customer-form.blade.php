{{-- @mercatura-view frontend.components.forms.customer-form @version 1 --}}
{{-- <form> wrapper with the customerForm Alpine data. Slot: the fields and buttons. --}}
@props(['action', 'method' => 'POST', 'type' => '', 'captchaField' => null, 'captchaAction' => null, 'id' => 'customer-fiscal-form'])
@php
    $config = [
        'type' => old('customer_type', $type),
        'privateType' => \App\Models\Customer::TYPE_PRIVATE,
        'publicAdminType' => \App\Models\Customer::TYPE_PUBLIC_ADMIN,
        'companyType' => 'Azienda',
        'messages' => [
            'tax_code' => __('frontend.forms.tax_code_error'),
            'vat_code' => __('frontend.forms.vat_code_error'),
            'ipa_code' => __('frontend.forms.ipa_code_error'),
            'cig_code' => __('frontend.forms.cig_code_error'),
            'password_mismatch' => __('frontend.forms.password_mismatch'),
        ],
    ];
@endphp
<form action="{{ $action }}" method="{{ strtoupper($method) === 'GET' ? 'GET' : 'POST' }}" id="{{ $id }}" x-data="customerForm(@js($config))" @submit="beforeSubmit($event)"
      @if($captchaField) data-captcha-field="{{ $captchaField }}" data-captcha-action="{{ $captchaAction }}" @endif {{ $attributes }}>
    @csrf
    @if(!in_array(strtoupper($method), ['GET', 'POST'], true))@method($method)@endif
    {{ $slot }}
</form>
