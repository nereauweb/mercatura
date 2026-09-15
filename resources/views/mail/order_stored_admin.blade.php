{{-- @mercatura-view mail.order_stored_admin @version 1 --}}
{{-- Order notification to the merchant. Data as built by Order::send_notification. --}}
@extends('mail.layout')
@section('title', __('mail.order_stored_admin.title', ['id' => $data['order_id'] ?? '']))
@section('content')
    <p style="margin:0 0 16px;">{{ __('mail.order_stored_admin.intro', ['brand' => config('brand.name'), 'id' => $data['order_id'] ?? '']) }}</p>
    @include('mail.partials.customer')
    @include('mail.partials.order')
@endsection
