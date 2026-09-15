{{-- @mercatura-view frontend.components.usp @version 1 --}}
{{-- Selling points from lang frontend.home.usp (title, text, icon). Shared by home and product page. --}}
@php $usps = __('frontend.home.usp'); @endphp
@if(is_array($usps) && $usps)
<section {{ $attributes->merge(['class' => 'grid gap-6 sm:grid-cols-2 lg:grid-cols-4']) }}>
    @foreach($usps as $usp)
        <div class="flex items-center gap-3">
            <x-frontend::icon :name="$usp['icon'] ?? 'bolt'" class="h-9 w-9 shrink-0 text-primary" />
            <div>
                <p class="font-bold uppercase text-primary">{{ $usp['title'] }}</p>
                <p class="text-sm font-semibold text-text-muted">{{ $usp['text'] }}</p>
            </div>
        </div>
    @endforeach
</section>
@endif
