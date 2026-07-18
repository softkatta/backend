<div class="card">
    <table class="card-header" width="100%">
        <tr>
            <td>
                @if(!empty($logo_file))
                    <img src="{{ $logo_file }}" alt="Logo" style="height: 26px; max-width: 90px;">
                @else
                    <div class="brand">{{ $company['name'] ?? 'SoftKatta' }}</div>
                @endif
                <div class="brand-sub">Employee Identity Card</div>
            </td>
            <td align="right" style="width: 70px;">
                <span class="badge">Staff</span>
            </td>
        </tr>
    </table>

    <div class="card-body">
        <table width="100%">
            <tr>
                <td style="width: 78px; vertical-align: top;">
                    @if(!empty($card['photo_uri']))
                        <img class="photo" src="{{ $card['photo_uri'] }}" alt="Photo">
                    @else
                        <div class="photo-fallback">{{ $card['initials'] }}</div>
                    @endif
                </td>
                <td style="vertical-align: top; padding-left: 10px;">
                    <div class="name">{{ $card['full_name'] }}</div>
                    <div class="meta"><strong>{{ $card['designation'] }}</strong></div>
                    <div class="meta">{{ $card['department'] }}</div>
                    <div class="meta">Joined: <strong>{{ $card['date_of_joining'] }}</strong></div>
                    <div class="code">{{ $card['employee_code'] }}</div>
                </td>
                <td style="width: 56px; vertical-align: bottom; text-align: right;">
                    @if(!empty($card['qr_uri']))
                        <img class="qr" src="{{ $card['qr_uri'] }}" alt="QR">
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <div class="card-footer">
        {{ $company['name'] ?? 'SoftKatta' }}
        @if(!empty($company['website']))
            · {{ $company['website'] }}
        @endif
        @if(!empty($card['email']))
            · {{ $card['email'] }}
        @endif
    </div>
</div>
