{{-- @mercatura-view mail.contact_admin @version 1 --}}
{{-- Contact form notification to the merchant. Data: company, name, surname, email, phone, activity, subject, message, consent_gdpr, subscribe_newsletter. --}}
@extends('mail.layout')
@section('title', __('mail.contact_admin.title'))
@section('content')
    <p style="margin:0 0 16px;">{{ __('mail.contact_admin.intro', ['brand' => config('brand.name')]) }}</p>
    @include('mail.partials.details', ['rows' => [
        __('mail.labels.company') => $data['company'] ?? null,
        __('mail.labels.name') => trim(($data['name'] ?? '').' '.($data['surname'] ?? '')),
        __('mail.labels.email') => $data['email'] ?? null,
        __('mail.labels.phone') => $data['phone'] ?? null,
        __('mail.labels.activity') => $data['activity'] ?? null,
        __('mail.labels.subject') => $data['subject'] ?? null,
        __('mail.labels.message') => $data['message'] ?? null,
        __('mail.labels.consent_gdpr') => $data['consent_gdpr'] ?? null,
        __('mail.labels.subscribe_newsletter') => $data['subscribe_newsletter'] ?? null,
    ]])
@endsection
