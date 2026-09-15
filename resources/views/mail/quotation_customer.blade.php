{{-- @mercatura-view mail.quotation_customer @version 1 --}}
{{-- Quotation request receipt to the customer. Data: quotation_date, customer_*, quotation_items, gdpr, newsletter, contacts_link. --}}
@extends('mail.layout')
@section('title', __('mail.quotation_customer.title'))
@section('content')
    <p style="margin:0 0 16px;">{{ __('mail.quotation_customer.greeting', ['name' => $data['customer_name'] ?? '']) }}</p>
    <p style="margin:0 0 16px;">{{ __('mail.quotation_customer.intro', ['brand' => config('brand.name'), 'date' => $data['quotation_date'] ?? '']) }}</p>
    @include('mail.partials.quotation-items', ['items' => $data['quotation_items'] ?? []])
    <p style="margin:0 0 16px;">{{ __('mail.quotation_customer.outro') }} <a href="{{ $data['contacts_link'] ?? route('frontend.contacts.index') }}" style="color:#1e40af;">{{ __('mail.quotation_customer.contacts') }}</a></p>
    <p style="margin:0;">{{ __('mail.signature', ['brand' => config('brand.name')]) }}</p>
@endsection
