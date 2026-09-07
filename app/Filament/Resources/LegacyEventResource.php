<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LegacyEventResource\Pages;
use App\Models\LegacyEvent;
use App\Support\FilamentNavigation;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;

class LegacyEventResource extends Resource
{
    protected static ?string $model = LegacyEvent::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_EVENTS;

    protected static ?string $navigationLabel = 'Imprezy archiwalne';

    protected static ?int $navigationSort = 90;

    protected static ?string $modelLabel = 'impreza archiwalna';

    protected static ?string $pluralModelLabel = 'imprezy archiwalne';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['office_id', 'name', 'client_name', 'client_nip', 'client_email', 'client_phone', 'legacy_status'];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('start_datetime', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('office_id')
                    ->label('Kod')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nazwa')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('start_datetime')
                    ->label('Wyjazd')
                    ->date('d.m.Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_datetime')
                    ->label('Powrót')
                    ->date('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('client_name')
                    ->label('Klient')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('client_nip')
                    ->label('NIP')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('contractor.name')
                    ->label('Kontrahent w systemie')
                    ->searchable()
                    ->sortable()
                    ->url(fn (LegacyEvent $record): ?string => $record->contractor_id
                        ? \App\Filament\Resources\ContractorResource::getUrl('edit', ['record' => $record->contractor_id])
                        : null)
                    ->color('primary')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('participant_count')
                    ->label('Uczestnicy')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('legacy_status')
                    ->label('Status')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn (?string $state): string => LegacyEvent::statusColor($state)),

                Tables\Columns\TextColumn::make('pilot')
                    ->label('Pilot')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(40),

                Tables\Columns\TextColumn::make('advance_payment')
                    ->label('Zaliczka')
                    ->suffix(' zł')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('legacy_status')
                    ->label('Status')
                    ->options(function (): array {
                        if (! Schema::hasTable('legacy_events')) {
                            return [];
                        }

                        return LegacyEvent::query()
                            ->whereNotNull('legacy_status')
                            ->distinct()
                            ->orderBy('legacy_status')
                            ->pluck('legacy_status', 'legacy_status')
                            ->all();
                    }),

                Tables\Filters\SelectFilter::make('contractor_id')
                    ->label('Kontrahent')
                    ->relationship('contractor', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('linked_contractor')
                    ->label('Powiązany kontrahent')
                    ->placeholder('Wszystkie')
                    ->trueLabel('Tylko z kontrahentem')
                    ->falseLabel('Bez kontrahenta')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('contractor_id'),
                        false: fn (Builder $query) => $query->whereNull('contractor_id'),
                    ),

                Filter::make('year')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('year')
                            ->label('Rok wyjazdu')
                            ->numeric(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['year'])) {
                            return $query;
                        }

                        return $query->whereYear('start_datetime', (int) $data['year']);
                    }),

                Filter::make('date_range')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('Wyjazd od'),
                        \Filament\Forms\Components\DatePicker::make('until')->label('Wyjazd do'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('start_datetime', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('start_datetime', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Podstawowe dane')
                ->description('Najważniejsze parametry imprezy archiwalnej')
                ->icon('heroicon-o-sparkles')
                ->columns([
                    'sm' => 2,
                    'xl' => 4,
                ])
                ->schema([
                    Infolists\Components\TextEntry::make('office_id')->label('Kod'),
                    Infolists\Components\TextEntry::make('name')->label('Nazwa')->weight('semibold')->columnSpan([
                        'sm' => 2,
                        'xl' => 2,
                    ]),
                    Infolists\Components\TextEntry::make('legacy_status')->label('Status')->badge()
                        ->color(fn (?string $state): string => LegacyEvent::statusColor($state)),
                    Infolists\Components\TextEntry::make('start_datetime')->label('Wyjazd')->dateTime('d.m.Y H:i'),
                    Infolists\Components\TextEntry::make('end_datetime')->label('Powrót')->dateTime('d.m.Y H:i'),
                    Infolists\Components\TextEntry::make('duration_days')->label('Dni'),
                    Infolists\Components\TextEntry::make('participant_count')->label('Uczestnicy'),
                    Infolists\Components\TextEntry::make('guardians_count')->label('Opiekunowie'),
                    Infolists\Components\TextEntry::make('free_count')->label(\App\Support\EventParticipantGroupLabels::GRATIS),
                ]),

            Infolists\Components\Section::make('Klient / Zamawiający')
                ->description('Dane kontaktowe i rozliczeniowe zamawiającego')
                ->icon('heroicon-o-user-group')
                ->columns([
                    'sm' => 1,
                    'lg' => 3,
                ])
                ->schema([
                    Infolists\Components\TextEntry::make('client_name')->label('Nazwa')->weight('semibold')->columnSpan([
                        'sm' => 1,
                        'lg' => 2,
                    ]),
                    Infolists\Components\TextEntry::make('client_nip')->label('NIP'),
                    Infolists\Components\TextEntry::make('client_street')->label('Ulica'),
                    Infolists\Components\TextEntry::make('client_city')->label('Miasto'),
                    Infolists\Components\TextEntry::make('client_contact_person')->label('Osoba kontaktowa')->icon('heroicon-o-user-circle'),
                    Infolists\Components\TextEntry::make('client_phone')->label('Telefon')->icon('heroicon-o-phone')->copyable(),
                    Infolists\Components\TextEntry::make('client_email')->label('Email')->icon('heroicon-o-envelope')->copyable(),
                    Infolists\Components\TextEntry::make('contractor.name')
                        ->label('Kontrahent w systemie')
                        ->placeholder('—')
                        ->url(fn (LegacyEvent $record): ?string => $record->contractor_id
                            ? \App\Filament\Resources\ContractorResource::getUrl('edit', ['record' => $record->contractor_id])
                            : null)
                        ->color('primary'),
                    Infolists\Components\TextEntry::make('legacy_purchaser_id')->label('ID zamawiającego (stary SOR)')->placeholder('—'),
                ]),

            Infolists\Components\Section::make('Transport i logistyka')
                ->icon('heroicon-o-truck')
                ->columns([
                    'sm' => 1,
                    'lg' => 3,
                ])
                ->schema([
                    Infolists\Components\TextEntry::make('pilot')->label('Pilot')->columnSpan([
                        'sm' => 1,
                        'lg' => 2,
                    ]),
                    Infolists\Components\TextEntry::make('driver')->label('Kierowca')->columnSpan([
                        'sm' => 1,
                        'lg' => 2,
                    ]),
                    Infolists\Components\TextEntry::make('bus_board_time')->label('Zbiórka')->dateTime('d.m.Y H:i'),
                    Infolists\Components\TextEntry::make('advance_payment')->label('Zaliczka')->money('PLN'),
                ]),

            Infolists\Components\Section::make('Uwagi')
                ->collapsible()
                ->icon('heroicon-o-chat-bubble-left-right')
                ->columns([
                    'sm' => 1,
                    'lg' => 2,
                ])
                ->schema([
                    Infolists\Components\TextEntry::make('order_note')
                        ->label('Uwagi zamówienia')
                        ->formatStateUsing(fn (mixed $state): HtmlString => self::formatRichTextAuto($state))
                        ->html()
                        ->columnSpanFull(),
                    Infolists\Components\TextEntry::make('notes')
                        ->label('Notatki')
                        ->formatStateUsing(fn (mixed $state): HtmlString => self::formatRichTextAuto($state))
                        ->html()
                        ->columnSpanFull(),
                    Infolists\Components\TextEntry::make('start_description')
                        ->label('Opis podstawienia')
                        ->formatStateUsing(fn (mixed $state): HtmlString => self::formatRichTextAuto($state))
                        ->html()
                        ->columnSpanFull(),
                    Infolists\Components\TextEntry::make('end_description')
                        ->label('Opis powrotu')
                        ->formatStateUsing(fn (mixed $state): HtmlString => self::formatRichTextAuto($state))
                        ->html()
                        ->columnSpanFull(),
                    Infolists\Components\TextEntry::make('diet_alert')
                        ->label('Diety')
                        ->formatStateUsing(fn (mixed $state): HtmlString => self::formatRichTextAuto($state))
                        ->html()
                        ->columnSpanFull(),
                    Infolists\Components\TextEntry::make('pilot_notes')
                        ->label('Uwagi pilota')
                        ->formatStateUsing(fn (mixed $state): HtmlString => self::formatRichTextAuto($state))
                        ->html()
                        ->columnSpanFull(),
                ]),

            Infolists\Components\Section::make('Program')
                ->description('Elementy programu imprezy archiwalnej')
                ->collapsible()
                ->icon('heroicon-o-map')
                ->schema([
                    Infolists\Components\ViewEntry::make('program_table')
                        ->hiddenLabel()
                        ->view('filament.infolists.legacy-event-program')
                        ->columnSpanFull(),
                ]),

            Infolists\Components\Section::make('Wydatki')
                ->description('Planowane i zrealizowane koszty')
                ->collapsible()
                ->icon('heroicon-o-banknotes')
                ->schema([
                    Infolists\Components\ViewEntry::make('payments_table')
                        ->hiddenLabel()
                        ->view('filament.infolists.legacy-event-payments')
                        ->columnSpanFull(),
                ]),

            Infolists\Components\Section::make('Wykonawcy')
                ->collapsible()
                ->collapsed()
                ->icon('heroicon-o-briefcase')
                ->schema([
                    Infolists\Components\ViewEntry::make('contractors_table')
                        ->hiddenLabel()
                        ->view('filament.infolists.legacy-event-contractors')
                        ->columnSpanFull(),
                ]),

            Infolists\Components\Section::make('Notatki powiązane')
                ->collapsible()
                ->collapsed()
                ->icon('heroicon-o-document-text')
                ->schema([
                    Infolists\Components\ViewEntry::make('notes_table')
                        ->hiddenLabel()
                        ->view('filament.infolists.legacy-event-notes')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    private static function decodeStateToRows(mixed $state): array
    {
        if ($state === null || $state === '') {
            return [];
        }

        if (is_string($state)) {
            $decoded = json_decode($state, true);

            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                return [];
            }

            $state = $decoded;
        }

        if (! is_array($state)) {
            return [];
        }

        return array_is_list($state) ? $state : [$state];
    }

    private static function renderCollectionHtml(mixed $state): string
    {
        $rows = self::decodeStateToRows($state);

        if ($rows === []) {
            return '<span class="text-sm text-gray-500">Brak danych</span>';
        }

        $html = '<div class="grid grid-cols-1 gap-3 xl:grid-cols-2">';

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $html .= self::renderRowHtml($row, is_int($index) ? $index : null);
        }

        $html .= '</div>';

        return $html;
    }

    private static function renderRowHtml(array $row, ?int $index = null): string
    {
        $title = $index !== null
            ? '<div class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-cyan-700">Pozycja '.($index + 1).'</div>'
            : '';

        $lines = '';
        foreach ($row as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $label = self::formatFieldLabel((string) $key);
            $formattedValue = self::formatFieldValue((string) $key, $value);
            $lines .= '<div class="mb-1"><span class="font-medium text-gray-700">'.e($label).':</span> '.$formattedValue.'</div>';
        }

        if ($lines === '') {
            return '';
        }

        return '<div class="rounded-xl border border-gray-200 bg-gradient-to-br from-white to-slate-50 p-3 shadow-sm">'.$title.$lines.'</div>';
    }

    private static function formatFieldLabel(string $key): string
    {
        $key = preg_replace('/([a-z])([A-Z])/', '$1 $2', $key) ?? $key;

        return ucfirst(str_replace('_', ' ', $key));
    }

    private static function formatFieldValue(string $key, mixed $value): string
    {
        if (is_array($value)) {
            return '<pre class="text-xs">'.e(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '').'</pre>';
        }

        if (is_bool($value)) {
            return $value ? 'Tak' : 'Nie';
        }

        $stringValue = (string) $value;
        $lowerKey = strtolower($key);

        if (str_contains($lowerKey, 'description') || str_contains($lowerKey, 'note')) {
            return self::formatRichTextAuto($stringValue)->toHtml();
        }

        return nl2br(e($stringValue));
    }

    private static function formatRichTextAuto(mixed $state): HtmlString
    {
        if ($state === null || $state === '') {
            return new HtmlString('<span class="text-sm text-gray-500">Brak danych</span>');
        }

        if (is_array($state)) {
            return new HtmlString(self::renderCollectionHtml($state));
        }

        $value = (string) $state;

        if (self::containsHtml($value)) {
            return new HtmlString($value);
        }

        return new HtmlString(nl2br(e($value)));
    }

    private static function containsHtml(string $value): bool
    {
        return (bool) preg_match('/<\/?[a-z][\s\S]*>/i', $value);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLegacyEvents::route('/'),
            'view' => Pages\ViewLegacyEvent::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
