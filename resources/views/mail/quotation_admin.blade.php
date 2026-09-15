{{-- @mercatura-view mail.quotation_admin @version 1 --}}
{{-- Quotation request notification to the merchant. Data as mail.quotation_customer. --}}
@extends('mail.layout')
@section('title', __('mail.quotation_admin.title'))
@section('content')
    <p style="margin:0 0 16px;">{{ __('mail.quotation_admin.intro', ['brand' => config('brand.name'), 'date' => $data['quotation_date'] ?? '']) }}</p>
    @include('mail.partials.customer')
    @include('mail.partials.quotation-items', ['items' => $data['quotation_items'] ?? []])
    @include('mail.partials.details', ['rows' => [
        __('mail.labels.consent_gdpr') => $data['gdpr'] ?? null,
        __('mail.labels.subscribe_newsletter') => $data['newsletter'] ?? null,
    ]])
@endsection
