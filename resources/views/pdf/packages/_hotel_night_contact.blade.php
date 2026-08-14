{{--
    Wspólny blok kontaktu hotelu dla nocy (PDF pilota / teczki).
    Oczekuje $day jako array z buildHotelPlanForPdf.
--}}
@php
    $hotelName = trim((string) ($day['hotel_name'] ?? ''));
    $hotelBranch = trim((string) ($day['hotel_branch'] ?? ''));
    $hotelAddress = trim((string) ($day['hotel_address'] ?? ''));
    $hotelPhone = trim((string) ($day['hotel_phone'] ?? ''));
    $hotelEmail = trim((string) ($day['hotel_email'] ?? ''));
@endphp
@if($hotelName !== '' || $hotelAddress !== '' || $hotelPhone !== '' || $hotelEmail !== '')
    <div style="margin:4px 0 8px; font-size:10px; line-height:1.35;">
        @if($hotelName !== '')
            <strong>{{ $hotelName }}</strong>
            @if($hotelBranch !== '')
                <span> — {{ $hotelBranch }}</span>
            @endif
            <br>
        @endif
        @if($hotelAddress !== '')
            <span>{{ $hotelAddress }}</span><br>
        @endif
        @if($hotelPhone !== '' || $hotelEmail !== '')
            <span>
                @if($hotelPhone !== '')
                    tel. {{ $hotelPhone }}
                @endif
                @if($hotelPhone !== '' && $hotelEmail !== '')
                    ·
                @endif
                @if($hotelEmail !== '')
                    {{ $hotelEmail }}
                @endif
            </span>
        @endif
    </div>
@endif
