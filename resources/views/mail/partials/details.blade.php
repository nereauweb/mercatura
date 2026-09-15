{{-- @mercatura-view mail.partials.details @version 1 --}}
{{-- Label/value table; $rows = [label => value], empty values skipped. --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px;margin:0 0 16px;">
    @foreach($rows as $label => $value)
        @if($value !== null && $value !== '')
        <tr>
            <th align="left" valign="top" style="padding:6px 8px 6px 0;white-space:nowrap;color:#64748b;font-weight:600;">{{ $label }}</th>
            <td valign="top" style="padding:6px 0;white-space:pre-line;">{{ $value }}</td>
        </tr>
        @endif
    @endforeach
</table>
