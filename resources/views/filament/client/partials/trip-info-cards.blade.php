@php
    use App\Services\ClientAccessService;
    use Illuminate\Support\Facades\Auth;

    /** @var \App\Models\Event $event */
    $event->loadMissing(['startPlace', 'eventTemplate']);

    $detailsVisible = Auth::user()?->can('viewClientPortalDetails', $event) ?? false;
    $access = app(ClientAccessService::class);

    $initials = static function (?string $name): string {
        $name = trim((string) $name);
        if ($name === '') {
            return '?';
        }

        $parts = preg_split('/\s+/u', $name) ?: [];
        $parts = array_values(array_filter($parts));

        if (count($parts) >= 2) {
            return mb_strtoupper(
                mb_substr($parts[0], 0, 1).mb_substr($parts[count($parts) - 1], 0, 1)
            );
        }

        return mb_strtoupper(mb_substr($name, 0, 2));
    };
@endphp

@if(! $detailsVisible)
    <div class="portal-card">
        <div class="portal-card-title">
            <p>Dostęp archiwalny</p>
            <span class="portal-status-pill">{{ $event->status }}</span>
        </div>
        <p class="portal-muted" style="margin:0;">{{ $access->archiveMessage($event) }}</p>
    </div>
@else
    <div class="portal-desktop-grid">
        <div>
            <div class="portal-card">
                <div class="portal-card-title">
                    <p>Informacje o wycieczce</p>
                    <span class="portal-status-pill">{{ $event->status }}</span>
                </div>
                <div class="portal-info-grid">
                    <div>
                        <p class="label">Nazwa</p>
                        <p class="value">{{ $event->name ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="label">Miejsce startu</p>
                        <p class="value">{{ $event->startPlace?->name ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="label">Data startu</p>
                        <p class="value">{{ $event->start_date?->format('d.m.Y') ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="label">Data końca</p>
                        <p class="value">{{ $event->end_date?->format('d.m.Y') ?: '—' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div class="portal-card">
                <div class="portal-card-title"><p>Kontakty</p></div>
                <div class="portal-contact-row">
                    <div class="portal-avatar client">{{ $initials($event->client_name) }}</div>
                    <div>
                        <p>{{ $event->client_name ?: 'Organizator / klient' }}</p>
                        <p>
                            Organizator
                            @if(filled($event->client_phone)) · {{ $event->client_phone }}@endif
                            @if(filled($event->client_email)) · {{ $event->client_email }}@endif
                        </p>
                    </div>
                </div>
            </div>

            @if(filled($event->diet_info))
                <div class="portal-card">
                    <div class="portal-card-title"><p>Diety</p></div>
                    <p class="label" style="margin:0 0 4px; font-size:11px; color:#888780;">Informacje o dietach (organizacyjne)</p>
                    <p style="margin:0; font-size:13px; white-space:pre-wrap;">{{ $event->diet_info }}</p>
                </div>
            @endif
        </div>
    </div>
@endif
