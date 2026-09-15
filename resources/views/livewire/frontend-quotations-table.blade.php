{{-- @mercatura-view livewire.frontend-quotations-table @version 1 --}}
<div>
	<x-frontend::customer.table :rows="$rows" :columns="$columns" :cells="$cells" :sort-by="$sortBy" :sort-direction="$sortDirection" :empty="__('frontend.account.no_quotations')" />
</div>
