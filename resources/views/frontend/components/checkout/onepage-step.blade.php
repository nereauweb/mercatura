{{-- @mercatura-view frontend.components.checkout.onepage-step @version 1 --}}
{{-- One accordion step of the onepage checkout: numbered header, "Modifica" on completed steps, body when current. --}}
@props(['number', 'title', 'current', 'reached', 'editable' => true])
@php $done = $number < $current; $open = $number === $current; @endphp
<section {{ $attributes->merge(['class' => 'rounded-card border '.($open ? 'border-primary' : 'border-border-muted')]) }} data-onepage-step="{{ $number }}">
    <header class="flex items-center justify-between gap-3 px-4 py-3 {{ $open ? 'bg-primary text-on-primary' : ($done ? 'bg-surface-muted text-primary' : 'bg-surface-muted text-text-muted') }}">
        <h2 class="flex items-center gap-2 text-sm font-bold uppercase"><span class="inline-flex h-6 w-6 items-center justify-center rounded-full border border-current text-xs">{{ $number }}</span>{{ $title }}</h2>
        @if($done && $editable && $number <= $reached)
            <button type="button" wire:click="goTo({{ $number }})" class="text-xs font-semibold underline">{{ __('frontend.onepage.edit') }}</button>
        @endif
    </header>
    @if($open)
    <div class="p-4">{{ $slot }}</div>
    @endif
</section>
