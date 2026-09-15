{{-- @mercatura-view frontend.components.checkout.steps @version 1 --}}
{{-- Progress of the four checkout steps. $current: 1..4. --}}
@props(['current' => 1, 'lastLabel' => null])
@php $steps = __('frontend.cart.steps'); if ($lastLabel) { $steps[3] = $lastLabel; } @endphp
<ol {{ $attributes->merge(['class' => 'grid grid-cols-2 overflow-hidden rounded-card border border-border-muted text-center text-sm sm:grid-cols-4']) }}>
    @foreach($steps as $index => $label)
        @php $number = $index + 1; @endphp
        <li class="px-3 py-2 {{ $number === $current ? 'bg-positive font-semibold text-on-dark' : ($number < $current ? 'text-positive' : 'text-text-muted') }} {{ $loop->last ? '' : 'border-r border-border-muted' }}" @if($number === $current) aria-current="step" @endif>{{ sprintf('%02d', $number) }}. {{ $label }}</li>
    @endforeach
</ol>
