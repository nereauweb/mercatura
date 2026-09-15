{{-- @mercatura-view mail.welcome @version 1 --}}
{{-- New account confirmation to the customer. Data: name. --}}
@extends('mail.layout')
@section('title', __('mail.welcome.title', ['brand' => config('brand.name')]))
@section('content')
    <p style="margin:0 0 16px;">{{ __('mail.welcome.greeting', ['name' => $data['name'] ?? '']) }}</p>
    <p style="margin:0 0 16px;">{{ __('mail.welcome.intro', ['brand' => config('brand.name')]) }}</p>
    @include('mail.partials.button', ['url' => route('frontend.auth.profile'), 'label' => __('mail.welcome.cta')])
    <p style="margin:0;">{{ __('mail.signature', ['brand' => config('brand.name')]) }}</p>
@endsection
