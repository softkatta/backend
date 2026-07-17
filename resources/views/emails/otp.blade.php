@extends('emails.layout')

@section('content')
    <h1 style="margin:0 0 12px;font-size:22px;line-height:1.35;font-weight:700;color:{{ $navy }};">{{ $title }}</h1>

    {!! $messageHtml !!}

    <div style="margin:24px 0 8px;text-align:center;">
        <div style="display:inline-block;padding:18px 28px;border-radius:14px;background:linear-gradient(135deg, {{ $navy }} 0%, {{ $primary }} 100%);color:#ffffff;font-size:32px;font-weight:700;letter-spacing:8px;">
            {{ $code }}
        </div>
    </div>

    <p style="margin:16px 0 0;font-size:13px;line-height:1.6;color:{{ $textMuted }};text-align:center;">
        This code expires in 10 minutes. If you did not request this, you can safely ignore this email.
    </p>
@endsection
