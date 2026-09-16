{{-- @mercatura-view frontend.public.navbar @version 2 --}}
{{-- Category navigation: "all categories" mega menu (desktop) / list (mobile) and the CMS pages flagged for the navbar.
     $nav_categories and $nav_extra_pages are shared by AppServiceProvider. --}}
<nav class="border-b border-border-muted bg-surface" aria-label="{{ __('frontend.nav.all_categories') }}">
	<div class="mx-auto max-w-7xl px-4">
		<div x-data="categoriesMenu" @click.outside="close()" @keydown.escape.window="close()" class="relative flex items-center gap-6 py-2">
			<button type="button" @click="toggle()" :aria-expanded="open" aria-controls="main-navigation" class="flex items-center gap-2 font-semibold uppercase text-primary hover:text-accent">
				<x-frontend::icon name="home" class="hidden h-5 w-5 md:block" />
				<span>{{ __('frontend.nav.all_categories') }}</span>
				<x-frontend::icon name="chevron-down" class="h-4 w-4 transition" ::class="{ 'rotate-180': open }" />
			</button>

			@if(!empty($nav_extra_pages))
			<ul class="hidden items-center gap-6 md:flex">
				@foreach($nav_extra_pages as $extra_page)
					<li><a href="{{ route('frontend.contents.page', ['slug' => $extra_page['slug']]) }}" class="text-sm font-semibold uppercase text-primary hover:text-accent">{{ $extra_page['title'] }}</a></li>
				@endforeach
			</ul>
			@endif

			{{-- Desktop: two-pane mega menu. Panes switch on click, as the menus this storefront replaces (no hover surprises). --}}
			<div id="main-navigation" x-cloak x-show="open" x-transition.opacity class="absolute left-0 top-full z-40 hidden w-full max-w-4xl grid-cols-5 rounded-b-card border border-border bg-surface shadow-xl md:grid">
				<ul class="col-span-2 border-r border-border-muted py-2 text-sm">
					<li>
						<button type="button" @click="select(0)" :class="active === 0 ? 'bg-surface-muted text-accent' : 'text-text'" class="flex w-full items-center gap-2 px-4 py-2 text-left font-semibold hover:bg-surface-muted">
							{{ __('frontend.nav.all_products') }}
						</button>
					</li>
					@foreach($nav_categories as $category)
					<li>
						<button type="button" @click="select({{ $loop->iteration }})" :class="active === {{ $loop->iteration }} ? 'bg-surface-muted text-accent' : 'text-text'" class="flex w-full items-center gap-2 px-4 py-2 text-left hover:bg-surface-muted">
							@if($category['icon'])
								<img src="/storage/categories/icons/{{ $category['icon'] }}" alt="" width="24" height="24" loading="lazy" class="h-6 w-6 object-contain">
							@endif
							<span>{{ $category['name'] }}</span>
						</button>
					</li>
					@endforeach
				</ul>
				<div class="col-span-3 p-4 text-sm">
					<div x-show="active === 0">
						<a href="{{ route('frontend.product.list') }}" class="font-semibold text-primary hover:text-accent">{{ __('frontend.nav.view_all_products') }}</a>
					</div>
					@foreach($nav_categories as $category)
					<div x-cloak x-show="active === {{ $loop->iteration }}">
						<a href="{{ route('frontend.category.show.by_slug', ['slug' => $category['slug']]) }}" class="font-semibold text-primary hover:text-accent">{{ __('frontend.nav.view_all_in_category') }}</a>
						@if(!empty($category['children']))
						<ul class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 border-t border-border-muted pt-3">
							@foreach($category['children'] as $subcategory)
								<li><a href="{{ route('frontend.category.show.by_slug', ['slug' => $subcategory['slug']]) }}" class="block py-0.5 hover:text-accent">{{ $subcategory['name'] }}</a></li>
							@endforeach
						</ul>
						@endif
					</div>
					@endforeach
				</div>
			</div>

			{{-- Mobile: flat list --}}
			<div x-cloak x-show="open" x-transition.opacity class="absolute left-0 top-full z-40 w-full rounded-b-card border border-border bg-surface shadow-xl md:hidden">
				<ul class="py-2 text-sm">
					<li><a href="{{ route('frontend.product.list') }}" class="block px-4 py-2 font-semibold hover:bg-surface-muted">{{ __('frontend.nav.all_products') }}</a></li>
					@foreach($nav_categories as $category)
						<li><a href="{{ route('frontend.category.show.by_slug', ['slug' => $category['slug']]) }}" class="flex items-center gap-2 px-4 py-2 hover:bg-surface-muted">
							@if($category['icon'])
								<img src="/storage/categories/icons/{{ $category['icon'] }}" alt="" width="24" height="24" loading="lazy" class="h-6 w-6 object-contain">
							@endif
							<span>{{ $category['name'] }}</span>
						</a></li>
					@endforeach
					@foreach($nav_extra_pages as $extra_page)
						<li><a href="{{ route('frontend.contents.page', ['slug' => $extra_page['slug']]) }}" class="block px-4 py-2 font-semibold uppercase hover:bg-surface-muted">{{ $extra_page['title'] }}</a></li>
					@endforeach
				</ul>
			</div>
		</div>
	</div>
</nav>
