{{-- @mercatura-view mail.order_cancelled @version 1 --}}
{{-- Order notification to the customer. Data as built by Order::send_notification. --}}
@extends('mail.layout')
@section('title', __('mail.order_cancelled.title', ['id' => $data['order_id'] ?? '']))
@section('content')
    <p style="margin:0 0 16px;">{{ __('mail.order_cancelled.greeting', ['name' => $data['customer_name'] ?? '']) }}</p>
    <p style="margin:0 0 16px;">{{ __('mail.order_cancelled.intro', ['brand' => config('brand.name'), 'id' => $data['order_id'] ?? '', 'status' => $data['order_status'] ?? '', 'tracking' => $data['order_tracking_code'] ?? '']) }}</p>
    @include('mail.partials.order')
    @include('mail.partials.button', ['url' => route('frontend.auth.order.list'), 'label' => __('mail.order.cta')])
    <p style="margin:0;">{{ __('mail.signature', ['brand' => config('brand.name')]) }}</p>
@endsection
