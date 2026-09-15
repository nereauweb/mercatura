{{-- @mercatura-view mail.register_admin @version 1 --}}
{{-- New customer registration notification to the merchant. Data: name, surname, company, email, phone, activity, customer_type, gdpr, terms, newsletter. --}}
@extends('mail.layout')
@section('title', __('mail.register_admin.title'))
@section('content')
    <p style="margin:0 0 16px;">{{ __('mail.register_admin.intro', ['brand' => config('brand.name')]) }}</p>
    @include('mail.partials.details', ['rows' => [
        __('mail.labels.company') => $data['company'] ?? null,
        __('mail.labels.name') => trim(($data['name'] ?? '').' '.($data['surname'] ?? '')),
        __('mail.labels.email') => $data['email'] ?? null,
        __('mail.labels.phone') => $data['phone'] ?? null,
        __('mail.labels.customer_type') => $data['customer_type'] ?? null,
        __('mail.labels.activity') => $data['activity'] ?? null,
        __('mail.labels.consent_gdpr') => $data['gdpr'] ?? null,
        __('mail.labels.consent_terms') => $data['terms'] ?? null,
        __('mail.labels.subscribe_newsletter') => $data['newsletter'] ?? null,
    ]])
@endsection
