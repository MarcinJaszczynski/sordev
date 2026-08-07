<?php

namespace App\Filament\Resources;

use App\Actions\Reservations\UpsertReservationAction;
use App\Data\UpsertReservationData;
use App\Filament\Forms\ReservationFormFields;
use App\Filament\Forms\ReservationFormOptions;
use App\Filament\Resources\ReservationResource\Pages;
use App\Filament\Resources\ReservationResource\Pages\ListReservations;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\Reservation;
use App\Support\FilamentNavigation;
use App\Support\MoneyFormatter;
use App\Support\ReservationPricingLabel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ReservationResource extends Resource
{
    protected static ?string $model = Reservation::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Wszystkie rezerwacje';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_EVENTS;

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Rezerwacja';

    protected static ?string $pluralModelLabel = 'Wszystkie rezerwacje';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Rezerwacja')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('event_id')
                            ->label('Impreza')
                            ->relationship('event', 'name')
                            ->searchable()
                            ->required()
                            ->live(),

                        ...ReservationFormFields::schema(new ReservationFormOptions(
                            showProgramPoint: true,
                            showSettlementCost: true,
                            showHotelNotes: true,
                            allowContractorCreate: true,
                        )),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('booking_reference')
                    ->label('Rezerwacja')
                    ->sortable()
                    ->html()
                    ->state(fn (Reservation $record): string => static::reservationSummaryColumnHtml($record)),

                Tables\Columns\TextColumn::make('event.name')
                    ->label('Impreza')
                    ->sortable()
                    ->html()
                    ->state(fn (Reservation $record): string => static::reservationEventColumnHtml($record))
                    ->url(fn (Reservation $record) => $record->event_id
                        ? EventResource::getUrl('edit', ['record' => $record->event_id])
                        : null),

                Tables\Columns\TextColumn::make('program_point_id')
                    ->label('Punkt programu')
                    ->html()
                    ->state(fn (Reservation $record): string => static::reservationProgramPointColumnHtml($record)),

                Tables\Columns\TextColumn::make('contractor.name')
                    ->label('Kontrahent')
                    ->sortable()
                    ->html()
                    ->state(fn (Reservation $record): string => static::reservationContractorColumnHtml($record)),

                Tables\Columns\TextColumn::make('reserved_at')
                    ->label('Terminy')
                    ->sortable()
                    ->html()
                    ->state(fn (Reservation $record): string => static::reservationDatesColumnHtml($record)),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Uwagi')
                    ->visibleFrom('lg')
                    ->html()
                    ->state(fn (Reservation $record): string => static::reservationNotesColumnHtml($record))
                    ->wrap(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->searchPlaceholder('Szukaj: nr rezerwacji, kod imprezy, kontrahent, punkt programu, daty, uwagi…')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(Reservation::$statuses),

                Tables\Filters\SelectFilter::make('event_id')
                    ->label('Impreza')
                    ->searchable()
                    ->getSearchResultsUsing(function (string $search): array {
                        return Event::query()
                            ->when(filled($search), function (Builder $query) use ($search): void {
                                $like = '%'.$search.'%';
                                $query->where(function (Builder $inner) use ($like): void {
                                    $inner->where('code', 'like', $like)
                                        ->orWhere('name', 'like', $like)
                                        ->orWhere('client_name', 'like', $like);
                                });
                            })
                            ->orderByDesc('start_date')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn (Event $event): array => [
                                $event->id => ListReservations::eventFilterLabel($event),
                            ])
                            ->all();
                    })
                    ->getOptionLabelUsing(function ($value): ?string {
                        if (blank($value)) {
                            return null;
                        }

                        $event = Event::query()->find($value);

                        return $event ? ListReservations::eventFilterLabel($event) : null;
                    }),

                Tables\Filters\SelectFilter::make('contractor_id')
                    ->label('Kontrahent')
                    ->relationship('contractor', 'name')
                    ->searchable(),

                Tables\Filters\Filter::make('expired')
                    ->label('Wygasłe rezerwacje')
                    ->query(fn (Builder $query) => $query->where('expires_at', '<', now()))
                    ->toggle(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordUrl(fn (Reservation $record): string => static::getUrl('edit', ['record' => $record]))
            ->actions([
                Tables\Actions\Action::make('view_details')
                    ->label('Szczegóły')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->tooltip('Podgląd rezerwacji bez otwierania formularza edycji.')
                    ->modalHeading(fn (Reservation $record): string => 'Rezerwacja: '.($record->booking_reference ?: '#'.$record->id))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Zamknij')
                    ->modalWidth('2xl')
                    ->infolist([
                        \Filament\Infolists\Components\Section::make('Dane rezerwacji')
                            ->columns(2)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('booking_reference')->label('Nr potwierdzenia dostawcy')->placeholder('—'),
                                \Filament\Infolists\Components\TextEntry::make('status')
                                    ->label('Status')
                                    ->formatStateUsing(fn (?string $state): string => Reservation::$statuses[$state] ?? ($state ?? '—')),
                                \Filament\Infolists\Components\TextEntry::make('event.name')->label('Impreza')->placeholder('—'),
                                \Filament\Infolists\Components\TextEntry::make('contractor.name')->label('Kontrahent')->placeholder('—'),
                                \Filament\Infolists\Components\TextEntry::make('participant_count')->label('Uczestnicy'),
                                \Filament\Infolists\Components\TextEntry::make('reserved_amount')
                                    ->label('Kwota')
                                    ->formatStateUsing(fn (Reservation $record): string => ReservationPricingLabel::format($record)),
                                \Filament\Infolists\Components\TextEntry::make('amount_basis')
                                    ->label('Rodzaj ceny')
                                    ->formatStateUsing(fn (?string $state): string => Reservation::$amountBases[$state ?? 'lump_sum'] ?? '—'),
                                \Filament\Infolists\Components\TextEntry::make('participant_scope')
                                    ->label('Liczba dotyczy')
                                    ->formatStateUsing(fn (?string $state): string => Reservation::$participantScopes[$state ?? 'all'] ?? '—'),
                                \Filament\Infolists\Components\TextEntry::make('reserved_at')->label('Data rezerwacji')->dateTime('d.m.Y H:i')->placeholder('—'),
                                \Filament\Infolists\Components\TextEntry::make('expires_at')->label('Ważna do')->dateTime('d.m.Y H:i')->placeholder('—'),
                                \Filament\Infolists\Components\TextEntry::make('notes')->label('Uwagi')->html()->columnSpanFull()->placeholder('—'),
                            ]),
                    ])
                    ->extraModalFooterActions([
                        Tables\Actions\Action::make('quick_edit')
                            ->label('Koryguj')
                            ->icon('heroicon-o-pencil-square')
                            ->color('primary')
                            ->modalHeading(fn (Reservation $record): string => 'Korekta: '.($record->booking_reference ?: '#'.$record->id))
                            ->modalWidth('2xl')
                            ->modalWidth(ReservationFormFields::MODAL_WIDTH)
                            ->fillForm(fn (Reservation $record): array => $record->only([
                                'booking_reference', 'status', 'participant_count', 'reserved_amount', 'currency_id',
                                'amount_basis', 'participant_scope',
                                'confirm_by', 'confirmed_at', 'deposit_due_at', 'deposit_paid_at',
                                'office_notes', 'notes', 'contractor_id',
                            ]))
                            ->form(fn (Reservation $record): array => ReservationFormFields::schema(new ReservationFormOptions(
                                showProgramPoint: true,
                                showSettlementCost: true,
                                editingReservation: $record,
                            )))
                            ->action(function (Reservation $record, array $data): void {
                                app(UpsertReservationAction::class)(new UpsertReservationData(
                                    attributes: $data,
                                    reservation: $record,
                                    attachmentData: $data,
                                    createdBy: auth()->id(),
                                ));
                            }),
                    ]),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function applyTableSearch(Builder $query, string $search): Builder
    {
        $term = trim($search);

        if ($term === '') {
            return $query;
        }

        if (static::looksLikeEventCode($term)) {
            return static::applyEventCodeSearch($query, $term);
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $inner) use ($term, $like): void {
            $inner->where('booking_reference', 'like', $like)
                ->orWhere('notes', 'like', $like);

            if (ctype_digit($term)) {
                $numeric = (int) $term;
                $inner->orWhere('id', $numeric)
                    ->orWhere('participant_count', $numeric);
            }

            foreach (Reservation::$statuses as $status => $label) {
                if (mb_stripos($label, $term) !== false) {
                    $inner->orWhere('status', $status);
                }
            }

            static::applyDateSearchConstraints($inner, $term);

            $inner->orWhereHas('event', function (Builder $eventQuery) use ($like, $term): void {
                $eventQuery->where('code', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('client_name', 'like', $like);

                if (ctype_digit($term)) {
                    $eventQuery->orWhere('id', (int) $term);
                }

                static::applyDateSearchConstraints($eventQuery, $term, 'start_date', 'end_date');
            });

            $inner->orWhereHas('contractor', fn (Builder $contractorQuery): Builder => $contractorQuery
                ->where('name', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('email', 'like', $like));

            $inner->orWhereHas('programPoint', function (Builder $pointQuery) use ($like): void {
                $pointQuery->where('name', 'like', $like)
                    ->orWhereHas('templatePoint', fn (Builder $templateQuery) => $templateQuery
                        ->where('name', 'like', $like))
                    ->orWhereHas('contractor', fn (Builder $contractorQuery) => $contractorQuery
                        ->where('name', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhere('email', 'like', $like));
            });

            $inner->orWhereHas('settlementCost', fn (Builder $costQuery): Builder => $costQuery
                ->where('name', 'like', $like));

            $inner->orWhereHas('creator', fn (Builder $creatorQuery): Builder => $creatorQuery
                ->where('name', 'like', $like)
                ->orWhere('email', 'like', $like));
        });
    }

    protected static function looksLikeEventCode(string $term): bool
    {
        return (bool) preg_match('/^\d{2}-[A-Z0-9]+$/i', $term);
    }

    protected static function applyEventCodeSearch(Builder $query, string $code): Builder
    {
        return $query->where(function (Builder $inner) use ($code): void {
            $inner->where('booking_reference', $code)
                ->orWhereHas('event', fn (Builder $eventQuery): Builder => $eventQuery->where('code', $code));
        });
    }

    /**
     * @param  list<string>  $dateColumns
     */
    protected static function applyDateSearchConstraints(
        Builder $query,
        string $term,
        string ...$dateColumns
    ): void {
        if ($dateColumns === []) {
            $dateColumns = ['confirm_by', 'confirmed_at', 'deposit_due_at', 'deposit_paid_at', 'reserved_at', 'expires_at', 'created_at'];
        }

        $term = trim($term);

        $parsed = static::parseExactSearchDate($term);
        if ($parsed !== null) {
            foreach ($dateColumns as $column) {
                $query->orWhereDate($column, $parsed);
            }

            return;
        }

        if (preg_match('/^(\d{1,2})\.(\d{4})$/', $term, $matches) === 1) {
            $month = (int) $matches[1];
            $year = (int) $matches[2];

            foreach ($dateColumns as $column) {
                $query->orWhere(function (Builder $dateQuery) use ($column, $month, $year): void {
                    $dateQuery->whereYear($column, $year)->whereMonth($column, $month);
                });
            }

            return;
        }

        if (preg_match('/^\d{4}$/', $term) === 1) {
            $year = (int) $term;

            foreach ($dateColumns as $column) {
                $query->orWhereYear($column, $year);
            }
        }
    }

    protected static function parseExactSearchDate(string $term): ?string
    {
        $term = trim($term);

        $formatPatterns = [
            'd.m.Y' => '/^\d{1,2}\.\d{1,2}\.\d{4}$/',
            'd.m.y' => '/^\d{1,2}\.\d{1,2}\.\d{2}$/',
            'Y-m-d' => '/^\d{4}-\d{1,2}-\d{1,2}$/',
            'd-m-Y' => '/^\d{1,2}-\d{1,2}-\d{4}$/',
            'd/m/Y' => '/^\d{1,2}\/\d{1,2}\/\d{4}$/',
        ];

        foreach ($formatPatterns as $format => $pattern) {
            if (preg_match($pattern, $term) !== 1) {
                continue;
            }

            try {
                $date = \Carbon\Carbon::createFromFormat($format, $term);

                return $date->toDateString();
            } catch (\Throwable) {
            }
        }

        return null;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'event',
                'programPoint.templatePoint',
                'programPoint.contractor',
                'contractor',
                'settlementCost',
                'creator',
            ]);
    }

    private static function formatMoneyPln(?float $amount, ?string $currencySymbol = 'PLN'): string
    {
        if ($amount === null) {
            return '—';
        }

        return MoneyFormatter::format($amount, $currencySymbol);
    }

    private static function formatDateTime(?\DateTimeInterface $value, string $dateFormat = 'd.m.Y', bool $withTime = true): string
    {
        if (! $value) {
            return '—';
        }

        return $withTime
            ? $value->format('d.m.Y H:i')
            : $value->format($dateFormat);
    }

    private static function statusBadgeHtml(string $status): string
    {
        $label = e(Reservation::$statuses[$status] ?? $status);
        [$bg, $fg] = match ($status) {
            'pending' => ['#fef3c7', '#92400e'],
            'confirmed', 'completed' => ['#dcfce7', '#166534'],
            'partially_confirmed' => ['#e0f2fe', '#0369a1'],
            'cancelled' => ['#fee2e2', '#991b1b'],
            'not_required' => ['#f3f4f6', '#374151'],
            default => ['#f3f4f6', '#374151'],
        };

        return "<span class='admin-table-pill' style='background:{$bg};color:{$fg}'>{$label}</span>";
    }

    private static function reservationSummaryColumnHtml(Reservation $record): string
    {
        $reference = filled($record->booking_reference)
            ? e($record->booking_reference)
            : '#'.$record->id;
        $participants = (int) ($record->participant_count ?? 0);
        $amount = e(ReservationPricingLabel::format($record));
        $scope = e(Reservation::$participantScopes[$record->participant_scope ?? 'all'] ?? '');

        return "<div class='admin-table-stack admin-table-stack-compact'>"
            ."<div class='admin-table-title'>{$reference}</div>"
            .static::statusBadgeHtml((string) $record->status)
            ."<div class='admin-table-meta'>{$scope}: ".e((string) $participants).'</div>'
            ."<div class='admin-table-meta'>Kwota: {$amount}</div>"
            .'</div>';
    }

    private static function reservationEventColumnHtml(Reservation $record): string
    {
        $name = e($record->event?->name ?? '—');
        $code = filled($record->event?->code) ? e($record->event->code) : null;
        $start = $record->event?->start_date?->format('d.m.Y') ?? '—';
        $end = $record->event?->end_date?->format('d.m.Y');
        $dates = $end && $end !== $start ? e($start).' – '.e($end) : e($start);

        $meta = $code ? $code.' · '.$dates : $dates;

        return "<div class='admin-table-stack'>"
            ."<div class='admin-table-title'>{$name}</div>"
            ."<div class='admin-table-meta'>{$meta}</div>"
            .'</div>';
    }

    private static function programPointLabel(?EventProgramPoint $point): string
    {
        if (! $point) {
            return '—';
        }

        return $point->templatePoint?->name ?? $point->name ?? ('Punkt #'.$point->id);
    }

    private static function reservationProgramPointColumnHtml(Reservation $record): string
    {
        $point = $record->programPoint;

        if (! $point && $record->settlementCost) {
            $costName = e($record->settlementCost->name ?? 'Koszt rozliczenia');
            $source = e($record->settlementCost->source_type ?? '—');

            return "<div class='admin-table-stack'>"
                ."<div class='admin-table-title'>{$costName}</div>"
                ."<div class='admin-table-meta'>Powiązanie: {$source}</div>"
                .'</div>';
        }

        if (! $point) {
            return "<span class='admin-table-muted'>—</span>";
        }

        $name = e(static::programPointLabel($point));
        $day = (int) ($point->day ?? 1);
        $order = (int) ($point->order ?? 0);
        $time = filled($point->start_time)
            ? e(substr((string) $point->start_time, 0, 5))
            : null;

        $badges = '';
        if ($point->is_hotel) {
            $badges .= "<span class='admin-table-pill' style='background:#dbeafe;color:#1e40af'>Hotel</span> ";
        }
        if ($point->is_transport) {
            $badges .= "<span class='admin-table-pill' style='background:#ffedd5;color:#9a3412'>Transport</span> ";
        }

        $meta = 'Dzień '.$day;
        if ($order > 0) {
            $meta .= ' · pkt '.$order;
        }
        if ($time) {
            $meta .= ' · godz. '.$time;
        }

        $pointContractor = $point->contractor?->name;
        $contractorLine = $pointContractor
            ? '<div class="admin-table-meta">Wykonawca punktu: '.e($pointContractor).'</div>'
            : '';

        return "<div class='admin-table-stack admin-table-stack-compact'>"
            ."<div class='admin-table-title'>{$name}</div>"
            .($badges !== '' ? "<div class='admin-table-meta'>{$badges}</div>" : '')
            .'<div class="admin-table-meta">'.e($meta).'</div>'
            .$contractorLine
            .'</div>';
    }

    private static function reservationContractorColumnHtml(Reservation $record): string
    {
        $name = e($record->contractor?->name ?? '—');
        $phone = filled($record->contractor?->phone) ? e($record->contractor->phone) : null;
        $email = filled($record->contractor?->email) ? e($record->contractor->email) : null;

        $lines = ["<div class='admin-table-title'>{$name}</div>"];
        if ($phone) {
            $lines[] = '<div class="admin-table-meta">'.$phone.'</div>';
        }
        if ($email) {
            $lines[] = '<div class="admin-table-meta">'.$email.'</div>';
        }

        return "<div class='admin-table-stack admin-table-stack-compact'>".implode('', $lines).'</div>';
    }

    private static function reservationDatesColumnHtml(Reservation $record): string
    {
        $confirmBy = $record->confirm_by
            ? \App\Support\Reservations\ReservationWorkflowDisplay::formatDate($record->confirm_by)
            : '—';
        $confirmed = $record->confirmed_at
            ? \App\Support\Reservations\ReservationWorkflowDisplay::formatDate($record->confirmed_at)
            : '—';
        $depositDue = $record->deposit_due_at
            ? \App\Support\Reservations\ReservationWorkflowDisplay::formatDate($record->deposit_due_at)
            : '—';
        $depositPaid = $record->deposit_paid_at
            ? \App\Support\Reservations\ReservationWorkflowDisplay::formatDate($record->deposit_paid_at)
            : '—';

        $depositColor = '#374151';
        if (
            $record->deposit_due_at
            && ! filled($record->deposit_paid_at)
            && \App\Support\Reservations\ReservationWorkflowDisplay::depositStatus($record) === 'overdue'
        ) {
            $depositColor = '#b91c1c';
        }

        return "<div class='admin-table-stack admin-table-stack-compact'>"
            .'<span class="admin-table-value">Potwierdzić do: '.e($confirmBy).'</span>'
            .'<span class="admin-table-value">Potwierdzono: '.e($confirmed).'</span>'
            .'<span class="admin-table-value-strong" style="color:'.$depositColor.'">Zaliczka do: '.e($depositDue).'</span>'
            .'<span class="admin-table-value">Zaliczka zapłacona: '.e($depositPaid).'</span>'
            .'</div>';
    }

    private static function isDateOnly(?\DateTimeInterface $value): bool
    {
        if (! $value) {
            return true;
        }

        return $value->format('H:i:s') === '00:00:00';
    }

    private static function reservationNotesColumnHtml(Reservation $record): string
    {
        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($record->office_notes ?? $record->notes ?? ''))) ?? '');

        if ($plain === '') {
            return "<span class='admin-table-muted'>—</span>";
        }

        $excerpt = e(Str::limit($plain, 160));

        return "<div class='admin-table-stack'><div class='admin-table-meta' style='white-space:normal'>{$excerpt}</div></div>";
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReservations::route('/'),
            'create' => Pages\CreateReservation::route('/create'),
            'edit' => Pages\EditReservation::route('/{record}/edit'),
        ];
    }
}
