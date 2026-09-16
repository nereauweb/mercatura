{{-- @mercatura-view mail.layout @version 1 --}}
{{-- Transactional mail frame: table layout, inline styles, system fonts, identity from config/brand.php.
     Sections: title (optional), content. --}}
@php
    $brand = config('brand');
    $base = rtrim(url('/'), '/');
    $phone = trim((string) $brand['contact']['phone']);
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>@yield('title', $brand['name'])</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f1f5f9;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background-color:#ffffff;border-radius:8px;overflow:hidden;">
                    <tr>
                        <td align="center" style="background-color:#1e40af;padding:24px;">
                            @php $mailLogo = $brand['logo_mail'] ?? $brand['logo']; @endphp
                            @if($mailLogo)
                                <img src="{{ $base.$mailLogo }}" alt="{{ $brand['name'] }}" width="{{ min((int) $brand['logo_width'], 200) }}" style="display:block;max-width:200px;height:auto;border:0;">
                            @else
                                <span style="font-size:20px;font-weight:700;color:#ffffff;">{{ $brand['name'] }}</span>
                            @endif
                        </td>
                    </tr>
                    @hasSection('title')
                    <tr>
                        <td style="padding:24px 24px 0;">
                            <h1 style="margin:0;font-size:22px;line-height:1.3;color:#1e40af;">@yield('title')</h1>
                        </td>
                    </tr>
                    @endif
                    <tr>
                        <td style="padding:24px;font-size:15px;line-height:1.6;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 24px;background-color:#f1f5f9;font-size:12px;line-height:1.6;color:#64748b;" align="center">
                            <strong>{{ $brand['legal_name'] ?: $brand['name'] }}</strong><br>
                            @if($phone)<a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}" style="color:#64748b;text-decoration:none;">{{ $phone }}</a> · @endif
                            @if($brand['contact']['email'])<a href="mailto:{{ $brand['contact']['email'] }}" style="color:#64748b;text-decoration:none;">{{ $brand['contact']['email'] }}</a> · @endif
                            <a href="{{ $base }}/" style="color:#64748b;text-decoration:none;">{{ parse_url($base, PHP_URL_HOST) }}</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
