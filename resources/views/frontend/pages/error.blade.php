{{-- @mercatura-view frontend.pages.error @version 2 --}}
@extends('frontend.public.layout')
@php $statusCode = (int) ($statusCode ?? 500); $copy = __('frontend.errors.'.$statusCode); $copy = is_array($copy) ? $copy : __('frontend.errors.500'); @endphp
@section('title', $copy['title'].' | '.config('brand.name'))
@section('head_meta')<meta name="robots" content="noindex,follow"><meta property="og:title" content="{{ $copy['title'] }} - {{ $statusCode }}" /><meta property="og:type" content="website" />@endsection
@section('content')
	<x-frontend::error-page :status="$statusCode" />
@endsection
