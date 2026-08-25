@php
    use App\Services\EventOrderingPartyService;
    use App\Services\PilotAccessService;
    use App\Support\ContractorContactDetails;
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Schema;

    /** @var \App\Models\Event $event */
    $event->loadMissing([
        'startPlace',
        'eventTemplate',
        'transportContractor',
        'assignedUser',
        'orderingContractors',
        'hotelStays.contractor',
        'hotelStays.contractorLocation',
        'hotelStays.programPoint.contractor',
        'hotelStays.programPoint.contractorLocation',
    ]);

    $pilotDetailsVisible = Auth::user()?->can('viewPilotDetails', $event) ?? false;
    $access = app(PilotAccessService::class);

    $formatClock = static function (mixed $state): string {
        if (blank($state)) {
            return '—';
        }

        return substr((string) $state, 0, 5);
    };

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

    $pickupDetails = '';
    if (Schema::hasColumn('events', 'pickup_place_details')) {
        $pickupDetails = trim(strip_tags((string) ($event->pickup_place_details ?? '')));
    }
    if ($pickupDetails === '') {
        $pickupDetails = (string) ($event->startPlace?->name ?: '');
    }

    $paying = max(0, (int) ($event->participant_count ?? 0));
    $gratis = max(0, (int) $event->resolveGratisCountForParticipantCount($paying ?: null));
    $peopleLabel = $gratis > 0
        ? sprintf('%d + %d (płacący + opiekunowie)', $paying, $gratis)
        : (string) $paying;

    $startDateLabel = $event->start_date?->format('d.m.Y');
    $computedReturnDate = $event->dateForProgramDay($event->resolveCoreProgramDaysCount());
    $returnDate = $event->end_date?->copy();
    if ($computedReturnDate && (! $returnDate || $computedReturnDate->gt($returnDate))) {
        $returnDate = $computedReturnDate;
    }
    $returnDateLabel = $returnDate?->format('d.m.Y');

    $formatDateTime = static function (?string $date, mixed $time) use ($formatClock): string {
        $clock = $formatClock($time);
        if ($date && $clock !== '—') {
            return $date.' '.$clock;
        }

        return $date ?: $clock;
    };

    $transportCompanyPhone = null;
    if (filled($event->transportContractor)) {
        $transportMeta = ContractorContactDetails::operationalMeta($event->transportContractor);
        $transportCompanyPhone = $transportMeta['phone'] ?? null;
    }

    $hotelLines = [];
    if (Schema::hasTable('event_hotel_stays')) {
        foreach ($event->hotelStays as $stay) {
            $contractor = $stay->contractor ?? $stay->programPoint?->contractor;
            if (! $contractor) {
                continue;
            }

            $location = $stay->contractorLocation ?? $stay->programPoint?->contractorLocation;
            $meta = ContractorContactDetails::operationalMeta($contractor, $location);
            $name = trim((string) $contractor->name);
            if ($name === '') {
                continue;
            }

            if (filled($meta['branch_name'])) {
                $name .= ' — '.$meta['branch_name'];
            }

            $block = 'Noc '.(int) $stay->day.': '.$name;
            if (filled($meta['address'])) {
                $block .= "\n".$meta['address'];
            }
            if (filled($meta['phone'])) {
                $block .= "\n".'tel. '.$meta['phone'];
            }

            $hotelLines[] = $block;
        }
    }

    $hasPilotNotes = Schema::hasColumn('events', 'pilot_notes')
        && trim(strip_tags((string) ($event->pilot_notes ?? ''))) !== '';

    $hasDiet = Schema::hasColumn('events', 'diet_info') && filled($event->diet_info);

    $routeDays = Schema::hasColumn('events', 'program_day_routes')
        ? $event->resolveCoreProgramDaysCount()
        : 0;

    $tripContact = app(EventOrderingPartyService::class)->tripContactForEvent($event);
    $tripContactMeta = null;
    $tripContactName = null;
    if (is_array($tripContact) && ($tripContact['contractor'] ?? null)) {
        $tripContactMeta = ContractorContactDetails::operationalMeta(
            $tripContact['contractor'],
            null,
            $tripContact['contact'] ?? null,
        );
        $tripContactName = trim((string) ($tripContactMeta['contact_name'] ?? ''));
        if ($tripContactName === '') {
            $tripContactName = trim((string) ($tripContactMeta['company_name'] ?? $tripContact['contractor']->displayLabel()));
        }
    }
@endphp

@if(! $pilotDetailsVisible)
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
                    <p>Podstawowe informacje</p>
                </div>
                <div class="portal-info-grid">
                    <div>
                        <p class="label">Tytuł wycieczki</p>
                        <p class="value">{{ $event->name ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="label">Termin wycieczki</p>
                        <p class="value">
                            @if($event->start_date)
                                {{ $event->start_date->format('d.m.Y') }}
                                @if($event->end_date && ! $event->end_date->isSameDay($event->start_date))
                                    – {{ $event->end_date->format('d.m.Y') }}
                                @endif
                            @else
                                —
                            @endif
                        </p>
                    </div>
                    <div>
                        <p class="label">Planowana liczba uczestników</p>
                        <p class="value">{{ $peopleLabel !== '' ? $peopleLabel : '—' }}</p>
                    </div>
                </div>
            </div>

            <div class="portal-card">
                <div class="portal-card-title">
                    <p>Podstawienie i wyjazd</p>
                </div>
                <div class="portal-info-grid">
                    @if(Schema::hasColumn('events', 'substitution_time'))
                        <div>
                            <p class="label">Podstawienie</p>
                            <p class="value">{{ $formatDateTime($startDateLabel, $event->substitution_time ?? null) }}</p>
                        </div>
                    @endif
                    @if(Schema::hasColumn('events', 'departure_time'))
                        <div>
                            <p class="label">Wyjazd</p>
                            <p class="value">{{ $formatDateTime($startDateLabel, $event->departure_time ?? null) }}</p>
                        </div>
                    @endif
                    @if(Schema::hasColumn('events', 'return_time'))
                        <div>
                            <p class="label">Powrót</p>
                            <p class="value">{{ $formatDateTime($returnDateLabel, $event->return_time ?? null) }}</p>
                        </div>
                    @endif
                </div>
                <p style="margin:10px 0 0; font-size:13px; color:#5F5E5A;">
                    <span style="display:block; font-size:11px; color:#888780; margin-bottom:2px;">Adres podstawienia autokaru</span>
                    {{ $pickupDetails !== '' ? $pickupDetails : '—' }}
                </p>
            </div>

            @if($routeDays > 0)
                <div class="portal-card">
                    <div class="portal-card-title"><p>Trasy przejazdu</p></div>
                    @for($day = 1; $day <= $routeDays; $day++)
                        @php
                            $date = $event->dateForProgramDay($day)?->format('d.m.Y');
                            $route = trim((string) ($event->programDayRoute($day) ?: ''));
                        @endphp
                        <div class="portal-route-row">
                            <span class="day">{{ $date ? "Dzień {$day} ({$date})" : "Dzień {$day}" }}</span>
                            @if($route !== '')
                                <span>{{ $route }}</span>
                            @else
                                <span class="empty">brak trasy</span>
                            @endif
                        </div>
                    @endfor
                </div>
            @endif
        </div>

        <div>
            <div class="portal-card">
                <div class="portal-card-title"><p>Transport</p></div>
                <div class="portal-info-grid">
                    <div>
                        <p class="label">Firma transportowa</p>
                        <p class="value">{{ $event->transport_company_name ?: ($event->transportContractor?->displayLabel() ?: '—') }}</p>
                    </div>
                    @if(filled($transportCompanyPhone))
                        <div>
                            <p class="label">Telefon firmy transportowej</p>
                            <p class="value">{{ $transportCompanyPhone }}</p>
                        </div>
                    @endif
                    <div>
                        <p class="label">Kierowca</p>
                        <p class="value">{{ $event->driver_name ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="label">Telefon kierowcy</p>
                        <p class="value">{{ $event->driver_phone ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="label">Rejestracja autokaru</p>
                        <p class="value">{{ $event->vehicle_registration ?: '—' }}</p>
                    </div>
                </div>
            </div>

            <div class="portal-card">
                <div class="portal-card-title"><p>Kontakty</p></div>
                @if($tripContactName)
                    <div class="portal-contact-row" style="margin-bottom:12px; padding-bottom:12px; border-bottom:1px solid #E8E7E3;">
                        <div class="portal-avatar client">{{ $initials($tripContactName) }}</div>
                        <div>
                            <p>{{ $tripContactName }}</p>
                            <p>
                                <span style="display:inline-block; font-size:11px; font-weight:600; color:#0F766E; background:#CCFBF1; padding:1px 6px; border-radius:4px; margin-right:4px;">Kontakt na wyjeździe</span>
                                @if(filled($tripContactMeta['phone'] ?? null)) · {{ $tripContactMeta['phone'] }}@endif
                            </p>
                            @if(filled($tripContactMeta['email'] ?? null))
                                <p style="font-size:12px; color:#888780; margin:2px 0 0;">{{ $tripContactMeta['email'] }}</p>
                            @endif
                        </div>
                    </div>
                @endif
                <div class="portal-contact-row">
                    <div class="portal-avatar client">{{ $initials($event->client_name) }}</div>
                    <div>
                        <p>{{ $event->client_name ?: 'Klient' }}</p>
                        <p>
                            Klient
                            @if(filled($event->client_phone)) · {{ $event->client_phone }}@endif
                        </p>
                    </div>
                </div>
                <div class="portal-contact-row">
                    <div class="portal-avatar pilot">{{ $initials($event->assignedUser?->name) }}</div>
                    <div>
                        <p>{{ $event->assignedUser?->name ?: 'Pilot' }}</p>
                        <p>
                            Pilot
                            @if(filled($event->assignedUser?->phone)) · {{ $event->assignedUser->phone }}@endif
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($hotelLines !== [])
        <div class="portal-card">
            <div class="portal-card-title"><p>Hotele</p></div>
            <div class="portal-muted" style="white-space:pre-line; margin:0;">{{ implode("\n\n", $hotelLines) }}</div>
        </div>
    @endif

    @if($hasPilotNotes)
        <div class="portal-card">
            <div class="portal-card-title"><p>Uwagi dla pilota</p></div>
            <div class="prose prose-sm max-w-none text-[#2C2C2A]">
                {!! $event->pilot_notes !!}
            </div>
        </div>
    @endif

    @if($hasDiet)
        <div class="portal-card">
            <div class="portal-card-title"><p>Diety</p></div>
            <p class="label" style="margin:0 0 4px; font-size:11px; color:#888780;">Diety specjalne</p>
            <p style="margin:0; font-size:13px; white-space:pre-wrap;">{{ $event->diet_info }}</p>
        </div>
    @endif
@endif
