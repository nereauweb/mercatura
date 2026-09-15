{{-- @mercatura-view mail.partials.order @version 1 --}}
{{-- Order summary shared by the order_* mails: $data as built by Order::send_notification. --}}
<h2 style="margin:0 0 8px;font-size:16px;color:#1e40af;">{{ __('mail.order.title', ['id' => $data['order_id'] ?? '']) }}</h2>
@include('mail.partials.details', ['rows' => [
    __('mail.order.date') => $data['order_date'] ?? null,
    __('mail.order.status') => $data['order_status'] ?? null,
    __('mail.order.payment_status') => $data['payment_status'] ?? null,
    __('mail.order.tracking_code') => $data['order_tracking_code'] ?? null,
    __('mail.order.address') => trim(implode(', ', array_filter([$data['order_address'] ?? null, trim(($data['order_zip_code'] ?? '').' '.($data['order_city'] ?? '')), $data['order_province'] ?? null, $data['order_country'] ?? null])), ', '),
    __('mail.order.notes') => $data['order_notes'] ?? null,
]])
@if(!empty($data['order_items']) && is_array($data['order_items']))
<h2 style="margin:0 0 8px;font-size:16px;color:#1e40af;">{{ __('mail.order.items_title') }}</h2>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px;margin:0 0 16px;border-collapse:collapse;">
    @foreach($data['order_items'] as $item)
    <tr>
        <td valign="top" style="padding:8px 0;border-top:1px solid #e2e8f0;">
            <strong>{{ $item['product_name'] ?? '' }}</strong> <span style="color:#64748b;">{{ $item['product_sku'] ?? '' }}</span><br>
            {{ $item['article_quantities'] ?? '' }}
            @if(!empty($item['printings']))<br>{{ $item['printings'] }}@endif
        </td>
        <td valign="top" align="right" style="padding:8px 0;border-top:1px solid #e2e8f0;white-space:nowrap;">{{ $item['quantity'] ?? '' }} × · € {{ $item['price'] ?? '' }}</td>
    </tr>
    @endforeach
</table>
@endif
@include('mail.partials.details', ['rows' => [
    __('mail.order.items_price') => isset($data['order_items_price']) ? '€ '.$data['order_items_price'] : null,
    __('mail.order.delivery_cost') => isset($data['order_delivery_cost']) ? '€ '.$data['order_delivery_cost'] : null,
    __('mail.order.total_price') => isset($data['order_total_price']) ? '€ '.$data['order_total_price'] : null,
    __('mail.order.total_tax') => isset($data['order_total_tax']) ? '€ '.$data['order_total_tax'] : null,
    __('mail.order.total_taxed_price') => isset($data['order_total_taxed_price']) ? '€ '.$data['order_total_taxed_price'] : null,
]])
