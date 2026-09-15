{{-- @mercatura-view frontend.public.header @version 2 --}}
{{-- Demo skin: the core header plus a ribbon that shows the skin is active. Logo and contacts already
     come from brand.php, so the override changes one thing only. Pages push the skin stylesheet. --}}
<div class="mercatura-demo-ribbon" style="background:#fde68a;color:#78350f;font:600 13px/1.6 system-ui,sans-serif;text-align:center;text-transform:uppercase;letter-spacing:.04em">{{ config('brand.name') }} · skin demo</div>
{{-- @mercatura-view frontend.public.header @version 2 --}}
{{-- Site header: utility bar, logo, brand contacts, search, cart. Identity from config/brand.php, copy from lang. --}}
@php
    $brand = config('brand');
    $phone = trim((string) $brand['contact']['phone']);
    $whatsapp = preg_replace('/\D+/', '', (string) $brand['contact']['whatsapp']);
    $addressParts = array_filter([
        $brand['contact']['address']['street'],
        trim($brand['contact']['address']['zip'].' '.$brand['contact']['address']['city']),
        $brand['contact']['address']['province'],
    ]);
    $addressLine = implode(', ', $addressParts);
@endphp
<header x-data="{ mobileOpen: false }" class="bg-primary-strong text-on-dark">
	<div class="hidden border-b border-white/10 md:block">
		<div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-1 text-sm">
			<nav aria-label="{{ __('frontend.nav.contacts') }}" class="flex gap-4">
				<a href="{{ route('frontend.contacts.index') }}" class="hover:underline">{{ __('frontend.nav.contacts') }}</a>
			</nav>
			<div class="flex items-center gap-4">
				@guest
					<a href="{{ route('frontend.auth.login') }}" class="hover:underline">{{ __('frontend.nav.login') }}</a>
					<a href="{{ route('frontend.auth.register') }}" class="hover:underline">{{ __('frontend.nav.register') }}</a>
				@endguest
				@auth
					<a href="{{ route('frontend.auth.reserved_area') }}" class="hover:underline">{{ __('frontend.nav.reserved_area') }}</a>
				@endauth
				<x-frontend::header.cart-buttons />
			</div>
		</div>
	</div>

	<div class="bg-primary">
		<div class="mx-auto max-w-7xl px-4 py-3">
			<div class="flex items-center gap-4">
				<a href="{{ route('frontend.home') }}" class="shrink-0">
					<img src="{{ $brand['logo'] }}" alt="{{ __('frontend.header.logo_alt', ['brand' => $brand['name']]) }}" width="{{ $brand['logo_width'] }}" height="{{ $brand['logo_height'] }}" class="h-10 w-auto md:h-12" fetchpriority="high">
				</a>

				<div class="hidden flex-1 flex-col gap-2 md:flex">
					@if($brand['contact']['email'] || $whatsapp || $addressLine)
					<ul class="flex flex-wrap justify-center gap-x-6 gap-y-1 text-sm">
						@if($brand['contact']['email'])
						<li><a href="mailto:{{ $brand['contact']['email'] }}" class="flex items-center gap-1 hover:underline"><x-frontend::icon name="envelope" class="h-4 w-4" />{{ $brand['contact']['email'] }}</a></li>
						@endif
						@if($whatsapp)
						<li><a href="https://api.whatsapp.com/send?phone={{ $whatsapp }}" target="_blank" rel="noopener" class="flex items-center gap-1 hover:underline"><x-frontend::icon name="chat" class="h-4 w-4" />{{ __('frontend.header.whatsapp') }}</a></li>
						@endif
						@if($addressLine)
						<li><a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($addressLine) }}" target="_blank" rel="noopener" class="flex items-center gap-1 hover:underline"><x-frontend::icon name="map-pin" class="h-4 w-4" />{{ $addressLine }}</a></li>
						@endif
					</ul>
					@endif
					<div class="flex items-center gap-4">
						<x-frontend::header.search id="header-search" class="flex-1" />
						@if($phone)
						<a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}" class="flex shrink-0 items-center gap-2 rounded-card border border-white/30 px-3 py-1 hover:bg-white/10">
							<x-frontend::icon name="phone" class="h-7 w-7" />
							<span>
								<span class="block text-xs font-light uppercase">{{ __('frontend.header.customer_service') }}</span>
								<span class="block text-lg font-bold leading-tight">{{ $phone }}</span>
							</span>
						</a>
						@endif
					</div>
				</div>

				<div class="ml-auto flex items-center gap-2 md:hidden">
					<x-frontend::header.cart-buttons />
					<button type="button" @click="mobileOpen = !mobileOpen" :aria-expanded="mobileOpen" aria-controls="header-mobile-menu" class="rounded p-1 hover:bg-white/10">
						<span class="sr-only" x-text="mobileOpen ? @js(__('frontend.nav.close_menu')) : @js(__('frontend.nav.open_menu'))">{{ __('frontend.nav.open_menu') }}</span>
						<x-frontend::icon name="menu" class="h-7 w-7" x-show="!mobileOpen" />
						<x-frontend::icon name="close" class="h-7 w-7" x-cloak x-show="mobileOpen" />
					</button>
				</div>
			</div>

			<div class="mt-3 md:hidden">
				<x-frontend::header.search id="header-search-mobile" />
			</div>

			<nav id="header-mobile-menu" x-cloak x-show="mobileOpen" x-transition.opacity class="mt-3 border-t border-white/10 pt-3 md:hidden" aria-label="{{ __('frontend.nav.menu') }}">
				<ul class="flex flex-col gap-2 text-sm uppercase">
					<li><a href="{{ route('frontend.contacts.index') }}" class="block py-1">{{ __('frontend.nav.contacts') }}</a></li>
					@guest
						<li><a href="{{ route('frontend.auth.login') }}" class="block py-1">{{ __('frontend.nav.login') }}</a></li>
						<li><a href="{{ route('frontend.auth.register') }}" class="block py-1">{{ __('frontend.nav.register') }}</a></li>
					@endguest
					@auth
						<li><a href="{{ route('frontend.auth.reserved_area') }}" class="block py-1">{{ __('frontend.nav.reserved_area') }}</a></li>
					@endauth
					@if($phone)
						<li><a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}" class="flex items-center gap-2 py-1"><x-frontend::icon name="phone" class="h-4 w-4" />{{ $phone }}</a></li>
					@endif
				</ul>
			</nav>
		</div>
	</div>
</header>
