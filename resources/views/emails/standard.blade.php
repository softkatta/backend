@extends('emails.layout')

@section('content')
    <h1 style="margin:0 0 18px;font-size:22px;line-height:1.35;font-weight:700;color:{{ $navy }};">{{ $title }}</h1>

    {!! $bodyHtml !!}

    @if(!empty($details))
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:8px;border:1px solid {{ $border }};border-radius:12px;overflow:hidden;background:#f8fafc;">
            @foreach($details as $label => $value)
                <tr>
                    <td style="padding:12px 16px;border-bottom:1px solid {{ $border }};font-size:13px;color:{{ $textMuted }};width:38%;vertical-align:top;">{{ $label }}</td>
                    <td style="padding:12px 16px;border-bottom:1px solid {{ $border }};font-size:14px;font-weight:600;color:{{ $text }};vertical-align:top;">{{ $value }}</td>
                </tr>
            @endforeach
        </table>
    @endif
@endsection
