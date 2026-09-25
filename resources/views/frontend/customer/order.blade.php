{{-- @mercatura-view frontend.customer.order @version 2 --}}
{{-- One order: items with printing file uploads, attachments, totals and status. --}}
@extends('frontend.public.layout')
@section('title', __('frontend.account.order_number', ['id' => $order->id]).' | '.config('brand.name'))
@section('head_meta')<meta name="robots" content="noindex,nofollow">@endsection
@section('content')
	@php $money = fn ($v) => number_format((float) $v, 2, ',', '.'); @endphp
	<div class="mx-auto max-w-7xl px-4 py-6">
		<div class="mb-4 flex flex-wrap items-center justify-between gap-2">
			<h1 class="text-2xl font-bold text-primary">{{ __('frontend.account.order_number', ['id' => $order->id]) }}</h1>
			<a href="{{ route('frontend.auth.order.list') }}" class="inline-flex items-center gap-1 text-sm text-accent hover:underline"><x-frontend::icon name="chevron-left" class="h-4 w-4" />{{ __('frontend.account.back_to_orders') }}</a>
		</div>
		<x-frontend::forms.errors />
		<div class="grid gap-6 md:grid-cols-4">
			<div class="space-y-4 md:col-span-3">
				@foreach($order->items as $item)
				<article class="rounded-card border border-border-muted bg-surface p-4">
					<div class="flex gap-4">
						@if($item->product_image_url)<img src="{{ $item->product_image_url }}" alt="{{ $item->product_name }}" width="96" height="96" loading="lazy" class="hidden h-24 w-24 shrink-0 object-contain md:block">@endif
						<div class="min-w-0 flex-1">
							<h2 class="font-bold text-primary">@if($item->product)<a href="{{ route('frontend.product.show.by_id', ['id' => $item->product->id]) }}">{{ $item->product_name }}</a>@else{{ $item->product_name }}@endif</h2>
							<p class="text-sm text-text-muted">{{ $item->product_sku }}</p>
							<div class="mt-2 overflow-x-auto">
								<table class="w-full text-sm">
									<thead><tr class="bg-surface-muted text-left"><th class="px-2 py-1">{{ __('frontend.cart.article') }}</th><th class="px-2 py-1">{{ __('frontend.cart.quantity') }}</th><th class="px-2 py-1">{{ __('frontend.cart.size') }}</th><th class="px-2 py-1">{{ __('frontend.cart.color') }}</th></tr></thead>
									<tbody>
										@foreach($item->articles as $line)<tr class="border-t border-border-muted"><td class="px-2 py-1">{{ $line->article_sku }}</td><td class="px-2 py-1">{{ $line->quantity }}</td><td class="px-2 py-1">{{ $line->article_size_label }}</td><td class="px-2 py-1">{{ $line->article_color_label }}</td></tr>@endforeach
										@if($item->customizations->count())
											<tr class="bg-surface-muted"><th colspan="4" class="px-2 py-1 text-left">{{ __('frontend.cart.printing') }}</th></tr>
											@foreach($item->customizations as $printing)<tr class="border-t border-border-muted"><td colspan="4" class="px-2 py-1">{{ $printing->label }}</td></tr>@endforeach
										@endif
									</tbody>
								</table>
							</div>
							@if($item->customizations->count())
							<div class="mt-3 rounded bg-danger-soft p-3 text-sm">
								<p class="font-semibold text-accent-strong">{{ __('frontend.account.print_file_missing') }}</p>
								<div class="mt-2 grid gap-3 sm:grid-cols-2">
									@foreach($item->customizations as $printing)
									<form action="{{ route('customer.order.printings.upload-image', $printing->id) }}" method="POST" enctype="multipart/form-data" class="rounded border border-border-muted bg-surface p-3">
										@method('PUT')@csrf
										<p class="text-xs font-semibold">{{ $printing->label }}</p>
										@if($printing->file)<p class="text-xs text-text-muted">{{ __('frontend.account.current_file') }}: <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($printing->file) }}" target="_blank" rel="noopener" class="text-accent underline">{{ __('frontend.account.download') }}</a></p>@endif
										<input type="file" name="variant_image" accept=".jpg,.jpeg,.png,.svg,.webp,.pdf,.ai,.eps,.tif,.tiff" class="mt-2 block w-full text-xs file:mr-2 file:rounded file:border-0 file:bg-surface-muted file:px-2 file:py-1" aria-label="{{ __('frontend.account.choose_file') }}">
										@error('variant_image')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
										<button type="submit" class="mt-2 rounded bg-primary px-3 py-1 text-xs font-bold uppercase text-on-primary">{{ __('frontend.account.upload') }}</button>
									</form>
									@endforeach
								</div>
							</div>
							@endif
							@if($item->shipping_date)
							<p class="mt-3 flex items-center gap-2 text-sm"><x-frontend::icon name="clock" class="h-4 w-4 text-primary" />{{ __('frontend.account.expected_delivery') }} <strong class="text-primary">{{ \Illuminate\Support\Carbon::parse($item->shipping_date)->format('d/m/Y') }}</strong></p>
							@endif
							<table class="mt-3 w-full text-sm">
								<thead><tr class="text-left text-text-muted"><th class="px-2 py-1 font-medium">{{ __('frontend.cart.quantity') }}</th><th class="px-2 py-1 font-medium">{{ __('frontend.cart.unit_price') }}</th><th class="px-2 py-1 font-medium">{{ __('frontend.cart.total_price') }}</th></tr></thead>
								<tbody><tr class="border-t border-border-muted font-semibold"><td class="px-2 py-1">{{ __('frontend.cart.pieces', ['count' => $item->quantity]) }}</td><td class="px-2 py-1">€ {{ $money($item->unit_price) }} <span class="font-normal text-text-muted">{{ __('frontend.cart.each_plus_vat') }}</span></td><td class="px-2 py-1">€ {{ $money($item->price) }} <span class="font-normal text-text-muted">{{ __('frontend.cart.plus_vat') }}</span></td></tr></tbody>
							</table>
						</div>
					</div>
				</article>
				@endforeach

				<section class="rounded-card border border-border-muted bg-surface p-4">
					<h2 class="font-bold text-primary">{{ __('frontend.account.attachments') }}</h2>
					<form action="{{ route('customer.order.upload-file', $order->id) }}" method="POST" enctype="multipart/form-data" class="mt-2 flex flex-wrap items-center gap-2">
						@csrf
						<input type="file" name="image" id="order-attachment" accept=".jpg,.jpeg,.png,.svg,.webp,.ai,.eps,.pdf" required class="flex-1 text-sm file:mr-2 file:rounded file:border-0 file:bg-surface-muted file:px-2 file:py-1" aria-label="{{ __('frontend.account.choose_file') }}">
						<button type="submit" class="rounded bg-primary px-3 py-1.5 text-sm font-bold uppercase text-on-primary">{{ __('frontend.account.upload') }}</button>
					</form>
					@error('image')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
					@if($order->media->count())
					<ul class="mt-3 space-y-2 text-sm">
						@foreach($order->media as $media)
						<li class="flex items-center justify-between gap-2 rounded bg-surface-muted px-3 py-2">
							<a href="{{ $media->getUrl() }}" target="_blank" rel="noopener" class="truncate text-primary hover:underline">{{ $media->file_name }} <span class="text-xs text-text-muted">({{ strtoupper($media->mime_type) }}, {{ number_format($media->size / 1024, 2) }} KB)</span></a>
							<form action="{{ route('customer.order.delete-file', [$order->id, $media->id]) }}" method="POST" onsubmit="return confirm(@js(__('frontend.account.delete_file_confirm')))">@csrf @method('DELETE')<button type="submit" class="rounded p-1 text-text-muted hover:text-danger" aria-label="{{ __('frontend.account.delete_file') }}"><x-frontend::icon name="close" class="h-4 w-4" /></button></form>
						</li>
						@endforeach
					</ul>
					@else
						<p class="mt-2 text-sm text-text-muted">{{ __('frontend.account.no_attachments') }}</p>
					@endif
				</section>
			</div>

			<aside class="md:col-span-1">
				<div class="overflow-hidden rounded-card border border-border-muted bg-surface text-sm md:sticky md:top-4">
					<h2 class="px-3 py-2 font-bold uppercase text-primary">{{ __('frontend.cart.total') }}</h2>
					<table class="w-full"><tbody>
						<tr class="border-t border-border-muted bg-surface-muted"><th scope="row" class="px-3 py-1 text-left font-medium">{{ __('frontend.cart.items_total') }}</th><td class="px-3 py-1 text-right font-bold">{{ $money($order->items_price) }}&nbsp;€</td></tr>
						<tr class="border-t border-border-muted bg-surface-muted"><th scope="row" class="px-3 py-1 text-left font-medium">{{ __('frontend.cart.delivery') }}</th><td class="px-3 py-1 text-right font-bold">{{ $order->delivery_cost > 0 ? $money($order->delivery_cost).' €' : __('frontend.cart.free') }}</td></tr>
						<tr class="border-t border-border-muted bg-surface-muted"><th scope="row" class="px-3 py-1 text-left font-medium">{{ __('frontend.cart.net_total') }}</th><td class="px-3 py-1 text-right font-bold">{{ $money($order->total_price) }}&nbsp;€</td></tr>
						<tr class="border-t border-border-muted bg-surface-muted"><th scope="row" class="px-3 py-1 text-left font-medium">{{ __('frontend.cart.vat_total') }}</th><td class="px-3 py-1 text-right font-bold">{{ $money($order->total_tax) }}&nbsp;€</td></tr>
						<tr class="border-t border-border bg-primary-soft"><th scope="row" class="px-3 py-2 text-left font-bold text-primary">{{ __('frontend.cart.cart_total') }}</th><td class="px-3 py-2 text-right text-lg font-bold text-primary">{{ $money($order->total_taxed_price) }}&nbsp;€</td></tr>
						<tr class="border-t border-border-muted"><th scope="row" class="px-3 py-1 text-left font-medium text-primary">{{ __('frontend.account.order_status') }}</th><td class="px-3 py-1 text-right font-bold">{{ \App\Models\Order::$status_names[$order->status] ?? $order->status }}</td></tr>
						<tr class="border-t border-border-muted"><th scope="row" class="px-3 py-1 text-left font-medium text-primary">{{ __('frontend.account.payment_status') }}</th><td class="px-3 py-1 text-right font-bold">{{ \App\Models\Order::$payment_status_names[$order->payment_status] ?? $order->payment_status }}</td></tr>
						<tr class="border-t border-border-muted"><th scope="row" class="px-3 py-1 text-left font-medium text-primary">{{ __('frontend.account.payment_method') }}</th><td class="px-3 py-1 text-right font-bold">{{ $order->payment_method }}</td></tr>
						@if($order->tracking_code)<tr class="border-t border-border-muted"><th scope="row" class="px-3 py-1 text-left font-medium text-primary">{{ __('frontend.account.tracking_code') }}</th><td class="px-3 py-1 text-right font-bold">{{ $order->tracking_code }}</td></tr>@endif
					</tbody></table>
				</div>
			</aside>
		</div>
	</div>
@endsection
