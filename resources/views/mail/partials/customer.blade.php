{{-- @mercatura-view mail.partials.customer @version 1 --}}
{{-- Customer block of order/quotation mails; $data keys prefixed customer_. --}}
<h2 style="margin:0 0 8px;font-size:16px;color:#1e40af;">{{ __('mail.order.customer_title') }}</h2>
@include('mail.partials.details', ['rows' => [
    __('mail.labels.company') => $data['customer_company'] ?? null,
    __('mail.labels.name') => trim(($data['customer_name'] ?? '').' '.($data['customer_surname'] ?? '')),
    __('mail.labels.email') => $data['customer_email'] ?? null,
    __('mail.labels.phone') => $data['customer_phone'] ?? null,
    __('mail.labels.customer_type') => $data['customer_type'] ?? null,
    __('mail.labels.activity') => $data['customer_activity'] ?? null,
    __('mail.labels.tax_code') => $data['customer_tax_code'] ?? null,
    __('mail.labels.vat_code') => $data['customer_vat_code'] ?? null,
    __('mail.labels.sdi_code') => $data['customer_sdi_code'] ?? null,
    __('mail.labels.ipa_code') => $data['customer_ipa_code'] ?? null,
    __('mail.labels.cig_code') => $data['customer_cig_code'] ?? null,
    __('mail.labels.pec') => $data['customer_pec'] ?? null,
]])
