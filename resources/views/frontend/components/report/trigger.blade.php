{{-- @mercatura-view frontend.components.report.trigger @version 1 --}}
{{-- Opens the report dialog (frontend.components.report.dialog, which must be on the page).
     Renders nothing for who may not report. Slot: the button content (default: the label). --}}
@php
    $user = auth()->user();
@endphp
@if(app(\App\Support\Reports\ReportAccess::class)->allows($user instanceof \App\Models\User ? $user : null))
<button type="button" x-data @click="$dispatch('report-open')" {{ $attributes->merge(['class' => 'cursor-pointer']) }}>
    @if($slot->isEmpty()){{ __('frontend.reports.open') }}@else{{ $slot }}@endif
</button>
@endif
