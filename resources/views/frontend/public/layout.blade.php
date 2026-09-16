{{-- @mercatura-view frontend.public.layout @version 2 --}}
{{-- The storefront page frame. Pages fill: title, head_meta, head_css, head_js, content, endpage_js.
     Injection points (docs/ARCHITECTURE.md §4): stacks head, scripts, header-before, header-after,
     footer-before, footer-after. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="csrf-token" content="{{ csrf_token() }}">
	<title>@yield('title', config('brand.meta.default_title') ?: config('brand.name').' | '.config('brand.tagline'))</title>
	<meta property="og:site_name" content="{{ config('brand.name') }}" />
	<meta property="og:locale" content="{{ app()->getLocale() }}_{{ strtoupper(app()->getLocale()) }}" />
	@yield('head_meta')
	@include('frontend.public.head')
	<x-frontend::seo.site-jsonld />
	@yield('head_css')
	@yield('head_js')
	@if(config('services.zendesk.widget_key'))
	<script id="ze-snippet" src="https://static.zdassets.com/ekr/snippet.js?key={{ config('services.zendesk.widget_key') }}" defer></script>
	@endif
	<x-frontend::tracking.gtm part="head" />
	@stack('head')
</head>
<body class="min-h-screen bg-surface font-sans text-text antialiased">
	<x-frontend::tracking.gtm part="body" />
	<a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:rounded focus:bg-primary focus:px-3 focus:py-2 focus:text-on-primary">{{ __('frontend.nav.skip_to_content') }}</a>

	@stack('header-before')
	@include('frontend.public.header')
	@stack('header-after')
	@include('frontend.public.navbar')

	<main id="main-content">
		@yield('content')
	</main>

	@stack('footer-before')
	@include('frontend.public.footer')
	@stack('footer-after')
	@include('frontend.public.endpage')

	@yield('endpage_js')

	<x-frontend::flash />

	@if(config('mercatura.storefront.quick_quote') === 'modal')
	<x-frontend::quick-quote />
	@endif

	{{-- Cookie consent banner (area 4h) --}}
	@php
		$cookieConsentConfig = config('cookie-consent');
		$alreadyConsentedWithCookies = \Illuminate\Support\Facades\Cookie::has($cookieConsentConfig['cookie_name']);
	@endphp
	@include('frontend.cookie-consent.index', compact('cookieConsentConfig', 'alreadyConsentedWithCookies'))

	@livewireScriptConfig
	@stack('scripts')
</body>
</html>
