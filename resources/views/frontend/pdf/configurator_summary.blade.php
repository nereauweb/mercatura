{{-- @mercatura-view frontend.pdf.configurator_summary @version 2 --}}
{{-- Printable summary of a configurator request: $data from FrontendProductController::build_articles_request. --}}
@extends('frontend.public.layout_pdf')
@section('title', config('brand.name').' – '.__('frontend.pdf.summary_title', ['date' => date('d/m/Y H:i')]))
@section('content')
	<h1>{{ config('brand.name') }}</h1>
	<h2>{{ __('frontend.pdf.summary_title', ['date' => date('d/m/Y H:i')]) }}</h2>
	<table>
		<tr class="total"><th colspan="2">{{ __('frontend.product.configurator.total_quantity') }}</th><td class="num">{{ $data['total_quantity'] }}</td></tr>
		@foreach($data['lines'] as $line)
			<tr><td style="{!! $line['column_1_style'] !!}">{!! $line['column_1'] !!}</td><td>{!! $line['column_2'] !!}</td><td class="num">{!! $line['column_3'] !!}</td></tr>
		@endforeach
		@if($data['total_additional_costs_amount'] > 0)
			<tr class="total"><th colspan="2">{{ __('frontend.product.configurator.additional_costs') }}</th><td class="num">{!! $data['total_additional_costs'] !!}</td></tr>
		@endif
		<tr class="total"><th colspan="2">{{ __('frontend.product.configurator.unit_price') }}</th><td class="num">{!! $data['unit_price'] !!}</td></tr>
		<tr class="total"><th colspan="2">{{ __('frontend.product.configurator.total_price') }}</th><td class="num">{!! $data['total_price'] !!}</td></tr>
		<tr class="total"><th colspan="2">{{ __('frontend.product.configurator.vat') }}</th><td class="num">{!! $data['total_vat'] !!}</td></tr>
		<tr class="grand"><th colspan="2">{{ __('frontend.product.configurator.total_taxed') }}</th><td class="num">{!! $data['total_taxed_price'] !!}</td></tr>
		<tr class="grand"><th colspan="2">{{ __('frontend.pdf.unit_taxed_price') }}</th><td class="num">{!! $data['unit_taxed_price'] !!}</td></tr>
	</table>
@endsection
