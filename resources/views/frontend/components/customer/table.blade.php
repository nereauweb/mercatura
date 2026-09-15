{{-- @mercatura-view frontend.components.customer.table @version 1 --}}
{{-- Sortable table for the customer's lists; used inside FrontendCustomerTable views. --}}
@props(['rows', 'columns', 'cells', 'sortBy', 'sortDirection', 'empty'])
<div class="overflow-x-auto rounded-card border border-border-muted bg-surface">
    @if(count($cells))
    <table class="w-full text-sm">
        <thead class="bg-surface-muted text-left">
            <tr>
                @foreach($columns as $column => $label)
                    <th scope="col" class="px-3 py-2 font-semibold"><button type="button" wire:click="sort('{{ $column }}')" class="inline-flex items-center gap-1 hover:text-accent" aria-label="{{ __('frontend.account.sort_by', ['column' => $label]) }}">{{ $label }}@if($sortBy === $column)<x-frontend::icon name="chevron-down" class="h-3 w-3 {{ $sortDirection === 'asc' ? 'rotate-180' : '' }}" />@endif</button></th>
                @endforeach
            </tr>
        </thead>
        <tbody wire:loading.class="opacity-60">
            @foreach($cells as $row)
                <tr class="border-t border-border-muted {{ $row['url'] ? 'cursor-pointer hover:bg-surface-muted' : '' }}" @if($row['url']) onclick="window.location='{{ $row['url'] }}'" @endif>
                    @foreach($columns as $column => $label)
                        <td class="px-3 py-2">@if($row['url'] && $loop->first)<a href="{{ $row['url'] }}" class="font-semibold text-primary">{{ $row['cells'][$column] }}</a>@else{{ $row['cells'][$column] }}@endif</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
    @else
        <p class="p-6 text-center text-text-muted">{{ $empty }}</p>
    @endif
</div>
{{ $rows->links('frontend.components.pagination') }}
