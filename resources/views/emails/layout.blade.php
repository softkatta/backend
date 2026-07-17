<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $title ?? $companyName }}</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f1f5f9;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid {{ $border }};box-shadow:0 8px 24px rgba(15,40,84,0.08);">
                <tr>
                    <td style="background:linear-gradient(135deg, {{ $navy }} 0%, {{ $primary }} 55%, {{ $teal }} 100%);padding:28px 32px;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                            <tr>
                                <td>
                                    @if(!empty($logoUrl))
                                        <img src="{{ $logoUrl }}" alt="{{ $companyName }}" height="42" style="display:block;max-width:180px;height:auto;border:0;">
                                    @else
                                        <div style="font-size:22px;font-weight:700;color:#ffffff;letter-spacing:0.2px;">{{ $companyName }}</div>
                                    @endif
                                    @if(!empty($tagline))
                                        <div style="margin-top:6px;font-size:13px;color:rgba(255,255,255,0.88);">{{ $tagline }}</div>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px;">
                        @yield('content')
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 32px 28px;border-top:1px solid {{ $border }};background:#f8fafc;">
                        <p style="margin:0 0 8px;font-size:12px;line-height:1.6;color:{{ $textMuted }};">
                            This is an automated message from {{ $companyName }}.
                        </p>
                        @if(!empty($supportEmail))
                            <p style="margin:0 0 8px;font-size:12px;line-height:1.6;color:{{ $textMuted }};">
                                Need help? Contact us at
                                <a href="mailto:{{ $supportEmail }}" style="color:{{ $primary }};text-decoration:none;">{{ $supportEmail }}</a>
                            </p>
                        @endif
                        @if(!empty($website))
                            <p style="margin:0;font-size:12px;line-height:1.6;color:{{ $textMuted }};">
                                <a href="{{ $website }}" style="color:{{ $primary }};text-decoration:none;">{{ $website }}</a>
                            </p>
                        @endif
                        <p style="margin:12px 0 0;font-size:11px;color:#94a3b8;">&copy; {{ $year }} {{ $companyName }}. All rights reserved.</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
