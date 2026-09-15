{{-- @mercatura-view frontend.pages.not_found @version 2 --}}
@extends('frontend.public.layout')
@section('title', __('frontend.errors.404.title').' | '.config('brand.name'))
@section('head_meta')<meta name="robots" content="noindex,follow"><meta property="og:title" content="{{ __('frontend.errors.404.title') }} - 404" /><meta property="og:type" content="website" />@endsection
@section('content')
	<x-frontend::error-page :status="404" />
@endsection
