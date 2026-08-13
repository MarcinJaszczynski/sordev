<?php

namespace App\Filament\Forms;

use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\Reservation;
use App\Support\Reservations\ReservationAttachmentStore;
use Filament\Forms;
use Filament\Forms\Get;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;

final class ReservationFormFields
{
    public const MODAL_WIDTH = '2xl';

    /** @return array<int, Forms\Components\Component> */
    public static function schema(ReservationFormOptions $options = new ReservationFormOptions): array
    {
        if ($options->simplified) {
            return self::simplifiedSchema($options);
        }

        return self::fullSchema($options);
    }

    /** @return array<int, Forms\Components\Component> */
    private static function simplifiedSchema(ReservationFormOptions $options): array
    {
        $fields = [
            self::contractorPlaceholder($options),

            Forms\Components\TextInput::make('booking_reference')
                ->label('Nr potwierdzenia dostawcy')
                ->helperText('Numer z potwierdzenia u kontrahenta — nie mylić z nr referencyjnym uczestnika.')
                ->maxLength(255)
                ->nullable(),

            ...ReservationWorkflowFields::workflowSection(),

            Forms\Components\Section::make('Kwota rezerwacji')
                ->collapsed()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    ...ParticipantPricingFields::reservationPricingFields(),
                    CurrencyConversionFields::currencySelect(),
                    ReservationAmountFields::make(),
                    CurrencyConversionFields::convertToggle(),
                    CurrencyConversionFields::plnPreview('reserved_amount'),
                ]),

            \FilamentTiptapEditor\TiptapEditor::make('office_notes')
                ->label('Notatki biura')
                ->helperText('Wewnętrzne ustalenia — widoczne w programie imprezy jako naklejka notatki.')
                ->columnSpanFull()
                ->nullable(),

            Forms\Components\FileUpload::make('pending_attachments')
                ->label('Załączniki')
                ->helperText('Np. PDF z potwierdzeniem rezerwacji.')
                ->directory('reservation-attachments')
                ->multiple()
                ->downloadable()
                ->openable()
                ->columnSpanFull()
                ->nullable(),

            self::historyPlaceholder($options),
        ];

        if ($options->showHotelNotes || $options->isHotelContext) {
            array_unshift($fields, self::hotelSelectionNotesPlaceholder($options->event));
        }

        return $fields;
    }

    /** @return array<int, Forms\Components\Component> */
    private static function fullSchema(ReservationFormOptions $options): array
    {
        $fields = [
            Forms\Components\TextInput::make('booking_reference')
                ->label('Nr potwierdzenia dostawcy')
                ->helperText('Numer z potwierdzenia u kontrahenta — nie mylić z nr referencyjnym uczestnika.')
                ->maxLength(255)
                ->nullable(),

            self::contractorSelect($options),

            ...self::optionalContextFields($options),

            ...ReservationWorkflowFields::workflowSection(),

            ...ParticipantPricingFields::reservationPricingFields(),

            CurrencyConversionFields::currencySelect(),
            ReservationAmountFields::make(),
            CurrencyConversionFields::convertToggle(),
            CurrencyConversionFields::plnPreview('reserved_amount'),

            \FilamentTiptapEditor\TiptapEditor::make('office_notes')
                ->label('Notatki biura')
                ->columnSpanFull()
                ->nullable(),

            \FilamentTiptapEditor\TiptapEditor::make('notes')
                ->label('Uwagi ogólne')
                ->columnSpanFull()
                ->nullable(),

            Forms\Components\FileUpload::make('pending_attachments')
                ->label('Załączniki')
                ->directory('reservation-attachments')
                ->multiple()
                ->downloadable()
                ->openable()
                ->columnSpanFull()
                ->nullable(),

            self::historyPlaceholder($options),
        ];

        return $fields;
    }

    /** @return array<string, mixed> */
    public static function defaultModalData(ReservationFormOptions $options = new ReservationFormOptions): array
    {
        return array_filter([
            'contractor_id' => $options->defaultContractorId,
            'program_point_id' => $options->defaultProgramPointId,
            'settlement_cost_id' => $options->defaultSettlementCostId,
            'participant_count' => $options->defaultParticipantCount ?? 1,
            'reserved_amount' => $options->defaultAmount,
            'currency_id' => $options->defaultCurrencyId,
            'amount_basis' => $options->defaultAmountBasis ?? 'lump_sum',
            'participant_scope' => $options->defaultParticipantScope ?? 'all',
            'convert_to_pln' => true,
            'status' => 'pending',
        ], fn ($value) => $value !== null);
    }

    /** @param array<string, mixed> $data */
    public static function normalizeSaveData(array $data): array
    {
        $data['amount_basis'] = (string) ($data['amount_basis'] ?? 'lump_sum');
        $data['participant_scope'] = (string) ($data['participant_scope'] ?? 'all');
        $data['convert_to_pln'] = (bool) ($data['convert_to_pln'] ?? true);

        unset($data['pending_attachments']);

        return $data;
    }

    /** @param array<string, mixed> $data */
    public static function persistAttachments(Reservation $reservation, array $data): void
    {
        $paths = $data['pending_attachments'] ?? [];

        if (! is_array($paths) || $paths === []) {
            return;
        }

        ReservationAttachmentStore::storeMany($reservation, $paths, auth()->id());
    }

    public static function hotelSelectionNotesPlaceholder(?Event $event = null): Forms\Components\Placeholder
    {
        return Forms\Components\Placeholder::make('hotel_selection_notes')
            ->label('Wybrane hotele w planie imprezy i ich uwagi')
            ->content(function (Get $get) use ($event): HtmlString|string {
                $resolved = $event ?? (($id = $get('event_id')) ? Event::with(['hotelStays.contractor'])->find($id) : null);

                if (! $resolved) {
                    return 'Brak wybranej imprezy.';
                }

                $stays = $resolved->hotelStays()->with('contractor')->get();
                if ($stays->isEmpty()) {
                    return 'Brak zaplanowanych noclegów w zakładce Hotele.';
                }

                $html = '<div class="space-y-4">';
                foreach ($stays as $stay) {
                    $hotelName = $stay->contractor ? $stay->contractor->name : 'Nie wybrano hotelu';
                    $html .= '<div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 p-3 text-sm">';
                    $html .= '<div class="font-semibold text-gray-900 dark:text-gray-100">Noc '.$stay->day.' — '.e($hotelName).'</div>';

                    if (filled($stay->offer_notes)) {
                        $html .= '<div class="mt-2"><strong class="text-xs text-gray-500 uppercase tracking-wide">Oferta / w cenie:</strong><br/>'.nl2br(e($stay->offer_notes)).'</div>';
                    }
                    if (filled($stay->notes)) {
                        $html .= '<div class="mt-2"><strong class="text-xs text-gray-500 uppercase tracking-wide">Uwagi operacyjne:</strong><br/>'.nl2br(e($stay->notes)).'</div>';
                    }
                    if (blank($stay->offer_notes) && blank($stay->notes)) {
                        $html .= '<div class="mt-1 text-xs text-gray-500">Brak uwag.</div>';
                    }
                    $html .= '</div>';
                }
                $html .= '</div>';

                return new HtmlString($html);
            })
            ->columnSpanFull();
    }

    private static function contractorPlaceholder(ReservationFormOptions $options): Forms\Components\Placeholder
    {
        return Forms\Components\Placeholder::make('contractor_display')
            ->label('Kontrahent')
            ->content(function () use ($options): string {
                if ($options->defaultProgramPointId) {
                    $point = EventProgramPoint::with('contractor')->find($options->defaultProgramPointId);
                    if ($point?->contractor) {
                        return $point->contractor->name;
                    }

                    return 'Brak kontrahenta na punkcie programu — uzupełnij wykonawcę punktu.';
                }

                if ($options->defaultContractorId) {
                    $contractor = \App\Models\Contractor::find($options->defaultContractorId);

                    return $contractor?->name ?? '—';
                }

                return 'Kontrahent zostanie pobrany z punktu programu.';
            })
            ->columnSpanFull();
    }

    private static function historyPlaceholder(ReservationFormOptions $options): Forms\Components\Placeholder
    {
        return Forms\Components\Placeholder::make('history_preview')
            ->label('Historia zmian ustaleń')
            ->content(function () use ($options): HtmlString|string {
                $record = $options->editingReservation;

                if (! $record?->exists || ! Schema::hasTable('reservation_history')) {
                    return 'Historia pojawi się po zapisaniu rezerwacji.';
                }

                $entries = $record->historyEntries()->with('user')->limit(12)->get();

                if ($entries->isEmpty()) {
                    return 'Brak wpisów w historii.';
                }

                $items = $entries->map(function ($entry): string {
                    $when = $entry->created_at?->format('d.m.Y H:i') ?? '—';
                    $who = e($entry->user?->name ?? 'System');
                    $description = e($entry->description ?? $entry->readable_action);

                    return "<li class=\"text-sm\"><span class=\"text-gray-500\">{$when}</span> · <span class=\"font-medium\">{$who}</span> — {$description}</li>";
                })->implode('');

                return new HtmlString("<ul class=\"space-y-1 list-none pl-0\">{$items}</ul>");
            })
            ->columnSpanFull()
            ->visible(fn (): bool => (bool) $options->editingReservation?->exists);
    }

    private static function contractorSelect(ReservationFormOptions $options): Forms\Components\Select
    {
        $select = Forms\Components\Select::make('contractor_id')
            ->label($options->isHotelContext ? 'Hotel / kontrahent' : 'Kontrahent')
            ->options(fn (): array => \App\Models\Contractor::filamentSelectOptions())
            ->searchable()
            ->preload()
            ->default($options->defaultContractorId)
            ->nullable()
            ->helperText(function () use ($options): ?string {
                if ($options->lockContractor) {
                    return 'Kontrahent jest pobierany z punktu programu.';
                }

                if (! $options->isHotelContext || ! $options->event) {
                    return $options->isHotelContext
                        ? 'Wybierz hotel z planu noclegów lub z listy kontrahentów.'
                        : null;
                }

                $hotels = $options->event->hotelStays()
                    ->with('contractor')
                    ->orderBy('day')
                    ->get()
                    ->map(fn ($stay) => $stay->contractor?->name
                        ? 'Noc '.$stay->day.': '.$stay->contractor->name
                        : null)
                    ->filter()
                    ->values();

                if ($hotels->isEmpty()) {
                    return 'Brak hoteli w planie noclegów — uzupełnij zakładkę Hotele.';
                }

                return 'Z planu noclegów: '.$hotels->implode(' · ');
            })
            ->visible(fn (): bool => ! $options->lockContractor);

        if ($options->allowContractorCreate) {
            $select
                ->createOptionForm(EventOrderingPartyFields::contractorCreateOptionSchema())
                ->createOptionUsing(fn (array $data): int => EventOrderingPartyFields::createContractorFromFormData($data));
        }

        return $select;
    }

    /** @return array<int, Forms\Components\Component> */
    private static function optionalContextFields(ReservationFormOptions $options): array
    {
        $fields = [];

        if ($options->showHotelNotes || $options->isHotelContext) {
            $fields[] = self::hotelSelectionNotesPlaceholder($options->event);
        }

        if ($options->showProgramPoint) {
            $fields[] = Forms\Components\Select::make('program_point_id')
                ->label('Punkt programu')
                ->options(fn (Get $get): array => self::programPointOptions($options->eventId ?? (($id = $get('event_id')) ? (int) $id : null)))
                ->searchable()
                ->default($options->defaultProgramPointId)
                ->nullable()
                ->live();
        }

        if ($options->showSettlementCost) {
            $fields[] = Forms\Components\Select::make('settlement_cost_id')
                ->label('Koszt rozliczenia')
                ->options(fn (Get $get): array => self::settlementCostOptions(
                    $options->settlementId ?? self::resolveSettlementId($options->eventId ?? (($id = $get('event_id')) ? (int) $id : null)),
                ))
                ->searchable()
                ->default($options->defaultSettlementCostId)
                ->nullable();
        }

        return $fields;
    }

    /** @return array<int, string> */
    private static function programPointOptions(?int $eventId): array
    {
        if (! $eventId) {
            return [];
        }

        return EventProgramPoint::query()
            ->where('event_id', $eventId)
            ->with('templatePoint')
            ->orderBy('day')
            ->orderBy('order')
            ->limit(400)
            ->get()
            ->mapWithKeys(fn (EventProgramPoint $point) => [
                $point->id => sprintf(
                    'Dzień %d • %s%s',
                    (int) ($point->day ?? 1),
                    $point->templatePoint?->name ?? $point->name ?? ('Punkt #'.$point->id),
                    $point->is_hotel ? ' 🏨' : '',
                ),
            ])
            ->all();
    }

    /** @return array<int, string> */
    private static function settlementCostOptions(?int $settlementId): array
    {
        if (! $settlementId) {
            return [];
        }

        return EventSettlementCost::query()
            ->where('settlement_id', $settlementId)
            ->orderBy('order')
            ->pluck('name', 'id')
            ->all();
    }

    private static function resolveSettlementId(?int $eventId): ?int
    {
        if (! $eventId) {
            return null;
        }

        return EventSettlement::query()
            ->where('event_id', $eventId)
            ->whereIn('status', ['draft', 'active', 'pilot_settled'])
            ->latest('id')
            ->value('id');
    }
}

final class ReservationFormOptions
{
    public function __construct(
        public ?int $eventId = null,
        public ?Event $event = null,
        public ?int $settlementId = null,
        public ?int $defaultContractorId = null,
        public ?int $defaultProgramPointId = null,
        public ?int $defaultSettlementCostId = null,
        public ?int $defaultParticipantCount = null,
        public mixed $defaultAmount = null,
        public ?int $defaultCurrencyId = null,
        public ?string $defaultAmountBasis = null,
        public ?string $defaultParticipantScope = null,
        public bool $isHotelContext = false,
        public bool $showHotelNotes = false,
        public bool $showProgramPoint = false,
        public bool $showSettlementCost = false,
        public bool $allowContractorCreate = false,
        public bool $simplified = false,
        public bool $lockContractor = false,
        public ?Reservation $editingReservation = null,
    ) {}
}
