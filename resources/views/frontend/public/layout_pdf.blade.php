{{-- @mercatura-view frontend.public.layout_pdf @version 2 --}}
{{-- Self-contained frame for PDF documents: no external assets, print-safe inline styles, identity from brand.php. --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
	<meta charset="utf-8">
	<title>@yield('title', config('brand.name'))</title>
	<style>
		@page { margin: 18mm 15mm; }
		body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; font-size: 11pt; color: #0f172a; }
		h1 { font-size: 16pt; margin: 0 0 4pt; color: #1e40af; text-transform: uppercase; }
		h2 { font-size: 11pt; margin: 0 0 12pt; color: #64748b; font-weight: normal; }
		table { width: 100%; border-collapse: collapse; }
		th, td { padding: 5pt 6pt; text-align: left; vertical-align: top; border-bottom: 1px solid #e2e8f0; }
		th { font-weight: 600; }
		td.num, th.num { text-align: right; white-space: nowrap; }
		tr.total th, tr.total td { background: #f1f5f9; font-weight: 700; }
		tr.grand th, tr.grand td { background: #dbeafe; color: #1e40af; font-weight: 700; }
		.footer { margin-top: 18pt; font-size: 9pt; color: #64748b; }
	</style>
	@yield('head_css')
</head>
<body>
	@yield('content')
	<p class="footer">{{ config('brand.legal_name') ?: config('brand.name') }}@if(config('brand.legal.vat')) · P.IVA {{ config('brand.legal.vat') }}@endif @if(config('brand.contact.email')) · {{ config('brand.contact.email') }}@endif</p>
</body>
</html>
