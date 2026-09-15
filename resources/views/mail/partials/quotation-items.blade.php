{{-- @mercatura-view mail.partials.quotation-items @version 1 --}}
{{-- Requested items of a quotation: $items (sku, name, quantity, printing, color, size, notes). --}}
@if(!empty($items) && is_array($items))
<h2 style="margin:0 0 8px;font-size:16px;color:#1e40af;">{{ __('mail.quotation.items_title') }}</h2>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px;margin:0 0 16px;border-collapse:collapse;">
    @foreach($items as $item)
    <tr>
        <td valign="top" style="padding:8px 0;border-top:1px solid #e2e8f0;">
            <strong>{{ $item['name'] ?? '' }}</strong> <span style="color:#64748b;">{{ $item['sku'] ?? '' }}</span><br>
            @foreach(['color', 'size', 'printing', 'notes'] as $key)
                @if(!empty($item[$key]))<span style="color:#64748b;">{{ __('mail.labels.'.$key) }}:</span> {{ $item[$key] }}<br>@endif
            @endforeach
        </td>
        <td valign="top" align="right" style="padding:8px 0;border-top:1px solid #e2e8f0;white-space:nowrap;">{{ __('mail.labels.quantity') }}: {{ $item['quantity'] ?? '' }}</td>
    </tr>
    @endforeach
</table>
@endif
