{{-- @mercatura-view frontend.components.contact-details @version 1 --}}
{{-- Brand contact details (phone, email, address, opening hours) as a list. --}}
@php
    $brand = config('brand');
    $phone = trim((string) $brand['contact']['phone']);
    $address = implode(', ', array_filter([$brand['contact']['address']['street'], trim($brand['contact']['address']['zip'].' '.$brand['contact']['address']['city']), $brand['contact']['address']['province']]));
@endphp
<ul {{ $attributes->merge(['class' => 'space-y-2 text-sm']) }}>
    @if($phone)<li class="flex items-start gap-2"><x-frontend::icon name="phone" class="mt-0.5 h-4 w-4 text-primary" /><a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}" class="hover:underline">{{ $phone }}</a></li>@endif
    @if($brand['contact']['email'])<li class="flex items-start gap-2"><x-frontend::icon name="envelope" class="mt-0.5 h-4 w-4 text-primary" /><a href="mailto:{{ $brand['contact']['email'] }}" class="hover:underline">{{ $brand['contact']['email'] }}</a></li>@endif
    @if($address)<li class="flex items-start gap-2"><x-frontend::icon name="map-pin" class="mt-0.5 h-4 w-4 text-primary" />{{ $address }}</li>@endif
    @if($brand['contact']['opening_hours'])<li class="flex items-start gap-2"><x-frontend::icon name="clock" class="mt-0.5 h-4 w-4 text-primary" />{{ $brand['contact']['opening_hours'] }}</li>@endif
</ul>
