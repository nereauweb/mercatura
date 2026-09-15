{{-- @mercatura-view mail.password_reset @version 1 --}}
{{-- Password reset link. Data: reset_link. --}}
@extends('mail.layout')
@section('title', __('mail.password_reset.title'))
@section('content')
    <p style="margin:0 0 16px;">{{ __('mail.greeting') }}</p>
    <p style="margin:0 0 16px;">{{ __('mail.password_reset.intro', ['brand' => config('brand.name')]) }}</p>
    @include('mail.partials.button', ['url' => $data['reset_link'] ?? url('/'), 'label' => __('mail.password_reset.cta')])
    <p style="margin:0 0 16px;font-size:13px;color:#64748b;">{{ __('mail.password_reset.ignore') }}</p>
    <p style="margin:0;">{{ __('mail.signature', ['brand' => config('brand.name')]) }}</p>
@endsection
