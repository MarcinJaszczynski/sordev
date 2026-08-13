<div class="page-footer">
    <div class="company">{{ $company['name'] ?? config('app.name') }}</div>
    <div class="address">
        {{ $company['address_line_1'] ?? '' }}
        @if(!empty($company['address_line_2']))
            &bull; {{ $company['address_line_2'] }}
        @endif
        @if(!empty($company['nip']))
            &bull; NIP {{ $company['nip'] }}
        @endif
        @if(!empty($company['email']))
            &bull; {{ $company['email'] }}
        @endif
        @if(!empty($company['phone']))
            &bull; tel. {{ $company['phone'] }}
        @endif
    </div>
</div>
