<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee ID Cards</title>
    <style>
        @page { margin: 16px 18px; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: {{ $colors['text'] }}; font-size: 10px; }
        .sheet { width: 100%; border-collapse: collapse; }
        .sheet td { width: 50%; vertical-align: top; padding: 8px; }

        .card {
            border: 1.5px solid {{ $colors['navy'] }};
            border-radius: 10px;
            overflow: hidden;
            height: 210px;
            page-break-inside: avoid;
        }
        .card-header {
            background: {{ $colors['navy'] }};
            color: #ffffff;
            padding: 8px 10px;
            height: 42px;
        }
        .card-header td { border: none; padding: 0; vertical-align: middle; color: #ffffff; }
        .brand { font-size: 11px; font-weight: bold; letter-spacing: 0.3px; }
        .brand-sub { font-size: 7px; color: #cbd5e1; padding-top: 2px; }
        .badge {
            background: {{ $colors['teal'] }};
            color: #0f172a;
            font-size: 7px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 3px 6px;
            border-radius: 4px;
        }
        .card-body { padding: 10px; }
        .photo {
            width: 68px;
            height: 84px;
            border: 1px solid {{ $colors['border'] }};
            border-radius: 6px;
            object-fit: cover;
            background: #f1f5f9;
        }
        .photo-fallback {
            width: 68px;
            height: 84px;
            border: 1px solid {{ $colors['border'] }};
            border-radius: 6px;
            background: #e2e8f0;
            text-align: center;
            line-height: 84px;
            font-size: 22px;
            font-weight: bold;
            color: {{ $colors['navy'] }};
        }
        .name { font-size: 13px; font-weight: bold; color: #0f172a; padding-bottom: 3px; }
        .meta { font-size: 8px; color: {{ $colors['text_muted'] }}; padding-bottom: 2px; }
        .meta strong { color: {{ $colors['text'] }}; }
        .code {
            display: inline-block;
            margin-top: 6px;
            background: #eff6ff;
            color: {{ $colors['blue'] }};
            font-size: 9px;
            font-weight: bold;
            font-family: DejaVu Sans Mono, monospace;
            padding: 3px 7px;
            border-radius: 4px;
            border: 1px solid #bfdbfe;
        }
        .qr { width: 52px; height: 52px; }
        .card-footer {
            border-top: 1px solid {{ $colors['border'] }};
            padding: 5px 10px;
            font-size: 7px;
            color: {{ $colors['text_muted'] }};
            background: #f8fafc;
        }
        .single-wrap { width: 340px; margin: 40px auto 0; }
    </style>
</head>
<body>
@if($single && $cards->count() === 1)
    <div class="single-wrap">
        @include('hr.partials.id-card', ['card' => $cards->first(), 'company' => $company, 'logo_file' => $logo_file, 'colors' => $colors])
    </div>
@else
    <table class="sheet">
        @foreach($cards->chunk(2) as $row)
            <tr>
                @foreach($row as $card)
                    <td>
                        @include('hr.partials.id-card', ['card' => $card, 'company' => $company, 'logo_file' => $logo_file, 'colors' => $colors])
                    </td>
                @endforeach
                @if($row->count() === 1)
                    <td></td>
                @endif
            </tr>
        @endforeach
    </table>
@endif
</body>
</html>
