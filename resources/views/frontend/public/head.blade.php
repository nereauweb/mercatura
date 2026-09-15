{{-- @mercatura-view frontend.public.head @version 3 --}}
{{-- Assets and icons for every storefront page. No skin conditionals: a skin overrides this file or pushes to the head stack. --}}
@php $favicons = rtrim(config('brand.favicon_path'), '/'); @endphp
<link rel="icon" type="image/x-icon" href="{{ $favicons }}/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="{{ $favicons }}/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="{{ $favicons }}/favicon-16x16.png">
<link rel="apple-touch-icon" sizes="180x180" href="{{ $favicons }}/apple-touch-icon.png">
<link rel="manifest" href="{{ $favicons }}/site.webmanifest">
<meta name="theme-color" content="{{ config('brand.theme_color') }}">
@vite(['resources/css/app.css', 'resources/js/app.js'])
@livewireStyles
