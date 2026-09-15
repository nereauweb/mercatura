{{-- @mercatura-view mail.request_quotation @version 2 --}}
{{-- Internal notification of a contact/quotation request: $message_content (App\Models\Message). --}}
@extends('mail.layout')
@section('title', __('mail.request_quotation.title'))
@section('content')
    <p style="margin:0 0 16px;">{{ __('mail.request_quotation.intro', ['brand' => config('brand.name')]) }}</p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px;">
        @foreach([
            'company' => $message_content->company,
            'name' => $message_content->name,
            'surname' => $message_content->surname,
            'email' => $message_content->email,
            'phone' => $message_content->phone,
            'activity' => $message_content->activity,
            'subject_field' => $message_content->subject,
            'message' => $message_content->message,
        ] as $key => $value)
            @if($value !== null && $value !== '')
            <tr>
                <th align="left" valign="top" style="padding:6px 8px 6px 0;white-space:nowrap;color:#64748b;font-weight:600;">{{ __('mail.request_quotation.'.$key) }}</th>
                <td valign="top" style="padding:6px 0;white-space:pre-line;">{{ $value }}</td>
            </tr>
            @endif
        @endforeach
    </table>
@endsection
