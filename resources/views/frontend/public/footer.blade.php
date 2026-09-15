{{-- @mercatura-view frontend.public.footer @version 2 --}}
{{-- Site footer: brand contacts, payments, social links, top categories, CMS pages, brands, legal line. --}}
@php
    $brand = config('brand');
    $phone = trim((string) $brand['contact']['phone']);
    $addressLine = implode(', ', array_filter([
        $brand['contact']['address']['street'],
        trim($brand['contact']['address']['zip'].' '.$brand['contact']['address']['city']),
        $brand['contact']['address']['province'],
    ]));
    $social = array_filter($brand['social'] ?? []);
    $footerCategories = array_slice($nav_categories ?? [], 0, 6);
@endphp
<footer class="bg-surface-muted text-text">
	<div class="mx-auto grid max-w-7xl gap-8 px-4 py-10 md:grid-cols-4">
		<div class="space-y-6 text-sm">
			<div>
				<h3 class="mb-3 text-base font-semibold">{{ __('frontend.footer.customer_service') }}</h3>
				<ul class="space-y-2">
					@if($brand['contact']['opening_hours'])
					<li class="flex items-start gap-2"><x-frontend::icon name="clock" class="mt-0.5 h-4 w-4 text-primary" />{{ $brand['contact']['opening_hours'] }}</li>
					@endif
					@if($phone)
					<li class="flex items-start gap-2"><x-frontend::icon name="phone" class="mt-0.5 h-4 w-4 text-primary" /><a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}" class="hover:underline">{{ $phone }}</a></li>
					@endif
					@if($brand['contact']['email'])
					<li class="flex items-start gap-2"><x-frontend::icon name="envelope" class="mt-0.5 h-4 w-4 text-primary" /><a href="mailto:{{ $brand['contact']['email'] }}" class="hover:underline">{{ $brand['contact']['email'] }}</a></li>
					@endif
					@if($addressLine)
					<li class="flex items-start gap-2"><x-frontend::icon name="map-pin" class="mt-0.5 h-4 w-4 text-primary" />{{ $addressLine }}</li>
					@endif
				</ul>
			</div>
			<div>
				<h3 class="mb-3 text-base font-semibold">{{ __('frontend.footer.secure_payments') }}</h3>
				<ul class="flex items-center gap-4">
					<li><img src="/demo/img/paypal.png" alt="PayPal" width="198" height="88" loading="lazy" class="h-8 w-auto"></li>
					<li><img src="/demo/img/visa.png" alt="Visa" width="134" height="84" loading="lazy" class="h-8 w-auto"></li>
					<li><img src="/demo/img/mastercard-logo.png" alt="Mastercard" width="146" height="88" loading="lazy" class="h-8 w-auto"></li>
				</ul>
			</div>
			@if($social)
			<div>
				<h3 class="mb-3 text-base font-semibold">{{ __('frontend.footer.follow_us') }}</h3>
				<ul class="flex items-center gap-3">
					@foreach($social as $network => $url)
						<li><a href="{{ $url }}" target="_blank" rel="noopener" class="block rounded-full bg-primary px-3 py-1 text-xs font-semibold uppercase text-on-primary hover:bg-accent">{{ $network }}</a></li>
					@endforeach
				</ul>
			</div>
			@endif
		</div>

		<div class="hidden space-y-6 text-sm md:block">
			@if($footerCategories)
			<div>
				<h3 class="mb-3 text-base font-semibold">{{ __('frontend.footer.top_categories') }}</h3>
				<ul class="space-y-1">
					@foreach($footerCategories as $category)
						<li><a href="{{ route('frontend.category.show.by_slug', ['slug' => $category['slug']]) }}" class="hover:text-accent">{{ $category['name'] }}</a></li>
					@endforeach
				</ul>
			</div>
			@endif
			<div>
				<h3 class="mb-3 text-base font-semibold">{{ __('frontend.footer.other_content') }}</h3>
				<ul class="space-y-1">
					<li><a href="{{ route('frontend.contacts.index') }}" class="hover:text-accent">{{ __('frontend.nav.contacts') }}</a></li>
					@foreach($nav_extra_pages ?? [] as $extra_page)
						<li><a href="{{ route('frontend.contents.page', ['slug' => $extra_page['slug']]) }}" class="hover:text-accent">{{ $extra_page['title'] }}</a></li>
					@endforeach
				</ul>
			</div>
		</div>

		@if(!empty($all_brands) && count($all_brands))
		<div class="hidden text-sm md:col-span-2 md:block">
			<h3 class="mb-3 text-base font-semibold">{{ __('frontend.footer.brands') }}</h3>
			<ul class="columns-3 space-y-1">
				@foreach($all_brands as $brandName)
					<li><a href="{{ route('frontend.product.list.brand', ['brand' => $brandName]) }}" class="hover:text-accent">{{ $brandName }}</a></li>
				@endforeach
			</ul>
		</div>
		@endif
	</div>
	<div class="bg-primary text-on-dark">
		<div class="mx-auto max-w-7xl px-4 py-4 text-sm">
			© {{ date('Y') }} {{ $brand['legal_name'] }}. {{ __('frontend.footer.rights') }}
			@if($brand['legal']['vat']) {{ __('frontend.footer.vat', ['vat' => $brand['legal']['vat']]) }} @endif
		</div>
	</div>
</footer>
