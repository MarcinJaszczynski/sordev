<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventSettlementResource\Pages;
use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\User;
use App\Support\ExecutiveAccess;
use App\Support\FilamentNavigation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Pages\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EventSettlementResource extends Resource
{
    protected static ?string $model = EventSettlement::class;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'Rozliczenia imprez';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_EVENTS;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Rozliczenie';

    protected static ?string $pluralModelLabel = 'Rozliczenia imprez';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Podstawowe informacje')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('event_id')
                        ->label('Impreza')
                        ->options(fn () => Event::orderByDesc('start_date')
                            ->limit(200)
                            ->get()
                            ->mapWithKeys(fn ($e) => [
                                $e->id => "[{$e->id}] {$e->name} ({$e->start_date?->format('d.m.Y')})",
                            ])
                        )
                        ->searchable()
                        ->required()
                        ->columnSpanFull()
                        ->reactive()
                        ->afterStateUpdated(fn ($state, Forms\Set $set) => static::fillEventDefaults($state, $set)),

                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options(EventSettlement::$statuses)
                        ->default('draft')
                        ->required(),

                    Forms\Components\Select::make('pilot_id')
                        ->label('Pilot')
                        ->options(fn () => User::orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->nullable(),

                    \FilamentTiptapEditor\TiptapEditor::make('notes')
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Raport pilota')
                ->description('Dane wprowadzone przez pilota w portalu /pilot.')
                ->columns(2)
                ->schema([
                    Forms\Components\Textarea::make('pilot_report_notes')
                        ->label('Uwagi pilota')
                        ->rows(4)
                        ->disabled()
                        ->dehydrated(false)
                        ->columnSpanFull()
                        ->afterStateHydrated(fn ($component, $record) => $component->state($record?->pilot_report_notes)),

                    Forms\Components\TextInput::make('reported_participant_count_display')
                        ->label('Liczba osób (wg pilota)')
                        ->disabled()
                        ->dehydrated(false)
                        ->afterStateHydrated(fn ($component, $record) => $component->state($record?->reported_participant_count)),

                    Forms\Components\TextInput::make('odometer_start_display')
                        ->label('Licznik — start')
                        ->disabled()
                        ->dehydrated(false)
                        ->afterStateHydrated(fn ($component, $record) => $component->state($record?->odometer_start)),

                    Forms\Components\TextInput::make('odometer_end_display')
                        ->label('Licznik — koniec')
                        ->disabled()
                        ->dehydrated(false)
                        ->afterStateHydrated(fn ($component, $record) => $component->state($record?->odometer_end)),

                    Forms\Components\TextInput::make('odometer_distance_display')
                        ->label('Przejechane km')
                        ->disabled()
                        ->dehydrated(false)
                        ->afterStateHydrated(fn ($component, $record) => $component->state($record?->odometer_distance)),

                    Forms\Components\TextInput::make('pilot_report_updated_at_display')
                        ->label('Ostatnia aktualizacja raportu')
                        ->disabled()
                        ->dehydrated(false)
                        ->afterStateHydrated(fn ($component, $record) => $component->state(
                            $record?->pilot_report_updated_at?->format('d.m.Y H:i')
                        )),
                ]),

            Forms\Components\Section::make('Sumy (auto-obliczane)')
                ->columns(2)
                ->schema([
                    Forms\Components\Placeholder::make('executive_only_notice')
                        ->label('Wynik finansowy')
                        ->content(ExecutiveAccess::settlementSummaryRestrictedMessage())
                        ->columnSpanFull()
                        ->visible(fn (?EventSettlement $record): bool => $record
                            && ! ExecutiveAccess::canViewSettlementFinancialSummary($record)),

                    Forms\Components\TextInput::make('planned_cost_pln')
                        ->label('Koszt planowany (PLN)')
                        ->default(0)
                        ->numeric()
                        ->readOnly()
                        ->suffix('PLN')
                        ->visible(fn (?EventSettlement $record): bool => ! $record || ExecutiveAccess::canViewSettlementFinancialSummary($record)),

                    Forms\Components\TextInput::make('actual_cost_pln')
                        ->label('Koszt rzeczywisty (PLN)')
                        ->default(0)
                        ->numeric()
                        ->readOnly()
                        ->suffix('PLN')
                        ->visible(fn (?EventSettlement $record): bool => ! $record || ExecutiveAccess::canViewSettlementFinancialSummary($record)),

                    Forms\Components\TextInput::make('participant_due_pln')
                        ->label('Należne od uczestników (PLN)')
                        ->default(0)
                        ->numeric()
                        ->readOnly()
                        ->suffix('PLN')
                        ->visible(fn (?EventSettlement $record): bool => ! $record || ExecutiveAccess::canViewSettlementFinancialSummary($record)),

                    Forms\Components\TextInput::make('participant_paid_pln')
                        ->label('Wpłacone przez uczestników (PLN)')
                        ->default(0)
                        ->numeric()
                        ->readOnly()
                        ->suffix('PLN')
                        ->visible(fn (?EventSettlement $record): bool => ! $record || ExecutiveAccess::canViewSettlementFinancialSummary($record)),

                    Forms\Components\TextInput::make('net_result_display')
                        ->label('Wynik netto (PLN)')
                        ->numeric()
                        ->readOnly()
                        ->suffix('PLN')
                        ->dehydrated(false)
                        ->afterStateHydrated(fn ($component, ?EventSettlement $record) => $component->state(
                            $record ? round((float) $record->net_result_pln, 2) : 0
                        ))
                        ->visible(fn (?EventSettlement $record): bool => $record && ExecutiveAccess::canViewFinalFinancialResults()),

                    Forms\Components\TextInput::make('pilot_expenses_planned')
                        ->label('Wydatki pilota – plan (PLN)')
                        ->numeric()
                        ->readOnly()
                        ->suffix('PLN')
                        ->dehydrated(false)
                        ->afterStateHydrated(fn ($component, $record) => $component->state(
                            $record
                                ? round((float) $record->costs()->where('paid_by', 'pilot')->whereNotIn('payment_status', ['cancelled'])->sum('planned_amount_pln'), 2)
                                : 0
                        )),

                    Forms\Components\TextInput::make('pilot_expenses_calculated')
                        ->label('Wydatki pilota – gotówka (PLN)')
                        ->numeric()
                        ->readOnly()
                        ->suffix('PLN')
                        ->dehydrated(false)
                        ->afterStateHydrated(fn ($component, $record) => $component->state(
                            $record
                                ? round((float) $record->pilotCashPreparations()->sum('pln_equivalent'), 2)
                                : 0
                        )),
                ]),
        ]);
    }

    protected static function fillEventDefaults($eventId, Forms\Set $set): void
    {
        if (! $eventId) {
            return;
        }
        $event = Event::with('programPoints')->find($eventId);
        if (! $event) {
            return;
        }
        // Jeśli pilot jest przypisany do imprezy
        if ($event->assigned_to) {
            $set('pilot_id', $event->assigned_to);
        }
    }

    private static function formatMoneyPln(float $amount): string
    {
        return number_format($amount, 2, ',', ' ').' PLN';
    }

    private static function settlementEventColumnHtml(EventSettlement $record): string
    {
        $name = e($record->event?->name ?? '—');
        $date = e($record->event?->start_date?->format('d.m.Y') ?? '—');
        $metaParts = [];
        if (filled($record->event?->code)) {
            $metaParts[] = e($record->event->code);
        }
        $metaParts[] = '#'.$record->id;
        $metaParts[] = $date;
        $meta = implode(' · ', $metaParts);

        return "<div class='admin-table-stack'>"
            ."<div class='admin-table-title'>{$name}</div>"
            ."<div class='admin-table-meta'>{$meta}</div>"
            .'</div>';
    }

    private static function settlementStatusPilotColumnHtml(EventSettlement $record): string
    {
        $statusLabel = e(EventSettlement::$statuses[$record->status] ?? $record->status);
        [$bg, $fg] = match ($record->status) {
            'draft' => ['#f3f4f6', '#374151'],
            'active' => ['#fef3c7', '#92400e'],
            'pilot_settled' => ['#e0f2fe', '#0369a1'],
            'closed' => ['#dcfce7', '#166534'],
            default => ['#f3f4f6', '#374151'],
        };
        $statusBadge = "<span class='admin-table-pill' style='background:{$bg};color:{$fg}'>{$statusLabel}</span>";

        $pilotName = $record->pilot?->name;
        $pilotLine = $pilotName
            ? '<div class="admin-table-meta">Pilot: '.e($pilotName).'</div>'
            : '<div class="admin-table-meta">Pilot: —</div>';

        $payoutLine = '';
        if (\Illuminate\Support\Facades\Schema::hasColumn('events', 'pilot_funds_paid') && $record->event?->assigned_to) {
            $paid = (bool) $record->event->pilot_funds_paid;
            [$pBg, $pFg] = $paid ? ['#dcfce7', '#166534'] : ['#fee2e2', '#991b1b'];
            $pLabel = e($paid ? 'Zaliczka wypłacona' : 'Zaliczka do wypłaty');
            $payoutLine = "<div class='admin-table-meta'><span class='admin-table-pill' style='background:{$pBg};color:{$pFg}'>{$pLabel}</span></div>";
        }

        return "<div class='admin-table-stack admin-table-stack-compact'>{$statusBadge}{$pilotLine}{$payoutLine}</div>";
    }

    private static function settlementCostsColumnHtml(EventSettlement $record): string
    {
        $planned = (float) $record->planned_cost_pln;
        $actual = (float) $record->actual_cost_pln;
        $diff = $actual - $planned;
        $diffColor = $diff > 0 ? '#b91c1c' : ($diff < 0 ? '#166534' : '#374151');

        $html = "<div class='admin-table-stack admin-table-stack-compact'>"
            .'<span class="admin-table-value">Plan: '.e(self::formatMoneyPln($planned)).'</span>'
            .'<span class="admin-table-value">Rzecz.: '.e(self::formatMoneyPln($actual)).'</span>'
            .'<span class="admin-table-value-strong" style="color:'.$diffColor.'">Różnica: '.e(self::formatMoneyPln($diff)).'</span>';

        if (ExecutiveAccess::canViewFinalFinancialResults()) {
            $net = (float) $record->net_result_pln;
            $netColor = $net >= 0 ? '#166534' : '#b91c1c';
            $html .= '<span class="admin-table-value-strong" style="color:'.$netColor.'">Wynik netto: '.e(self::formatMoneyPln($net)).'</span>';
        }

        return $html.'</div>';
    }

    private static function settlementParticipantsColumnHtml(EventSettlement $record): string
    {
        $due = (float) $record->participant_due_pln;
        $paid = (float) $record->participant_paid_pln;
        $balance = $paid - $due;
        $balanceColor = $balance >= 0 ? '#166534' : '#b91c1c';

        return "<div class='admin-table-stack admin-table-stack-compact'>"
            .'<span class="admin-table-value">Należne: '.e(self::formatMoneyPln($due)).'</span>'
            .'<span class="admin-table-value">Wpłacone: '.e(self::formatMoneyPln($paid)).'</span>'
            .'<span class="admin-table-value-strong" style="color:'.$balanceColor.'">Saldo: '.e(self::formatMoneyPln($balance)).'</span>'
            .'</div>';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['event', 'pilot']))
            ->columns([
                Tables\Columns\TextColumn::make('event.name')
                    ->label('Impreza')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->html()
                    ->state(fn (EventSettlement $record): string => static::settlementEventColumnHtml($record))
                    ->url(fn (EventSettlement $record) => $record->event_id
                        ? EventResource::getUrl('edit', ['record' => $record->event_id])
                        : null),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status / pilot')
                    ->sortable()
                    ->html()
                    ->state(fn (EventSettlement $record): string => static::settlementStatusPilotColumnHtml($record)),

                Tables\Columns\TextColumn::make('planned_cost_pln')
                    ->label('Koszty')
                    ->sortable()
                    ->visibleFrom('md')
                    ->visible(fn (?EventSettlement $record): bool => ! $record || ExecutiveAccess::canViewSettlementFinancialSummary($record))
                    ->html()
                    ->state(fn (EventSettlement $record): string => static::settlementCostsColumnHtml($record)),

                Tables\Columns\TextColumn::make('participant_due_pln')
                    ->label('Wpłaty uczestników')
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('participant_paid_pln', $direction))
                    ->visibleFrom('lg')
                    ->visible(fn (?EventSettlement $record): bool => ! $record || ExecutiveAccess::canViewSettlementFinancialSummary($record))
                    ->html()
                    ->state(fn (EventSettlement $record): string => static::settlementParticipantsColumnHtml($record)),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->date('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(EventSettlement::$statuses),

                Tables\Filters\SelectFilter::make('pilot_id')
                    ->label('Pilot')
                    ->options(fn () => User::orderBy('name')->pluck('name', 'id'))
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\Action::make('open_event')
                    ->label('Impreza')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn ($record) => $record->event_id ? EventResource::getUrl('edit', ['record' => $record->event_id]) : null)
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('import')
                    ->label('Importuj z imprezy')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Importuj koszty z imprezy')
                    ->modalDescription('Importuje wszystkie aktywne punkty programu jako planowane koszty. Istniejące pozycje zostaną pominięte.')
                    ->action(function ($record) {
                        $record->importFromEvent();
                        $record->refresh();
                    })
                    ->visible(fn ($record) => $record->status === 'draft'),

                Tables\Actions\Action::make('recalc_pilot')
                    ->label('Oblicz gotówkę pilota')
                    ->icon('heroicon-o-calculator')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->recalculatePilotCash();
                        $record->refresh();
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Podsumowanie rozliczenia')
                ->columns(4)
                ->schema([
                    Infolists\Components\TextEntry::make('event.name')
                        ->label('Impreza')
                        ->columnSpan(2),

                    Infolists\Components\TextEntry::make('status_label')
                        ->label('Status')
                        ->badge(),

                    Infolists\Components\TextEntry::make('pilot.name')
                        ->label('Pilot')
                        ->placeholder('—'),

                    Infolists\Components\TextEntry::make('executive_only_notice')
                        ->label('Wynik finansowy')
                        ->state(fn (): string => ExecutiveAccess::settlementSummaryRestrictedMessage())
                        ->columnSpanFull()
                        ->visible(fn (?EventSettlement $record): bool => $record && ! ExecutiveAccess::canViewSettlementFinancialSummary($record)),

                    Infolists\Components\TextEntry::make('pilot.birth_date')
                        ->label('Data urodzenia pilota')
                        ->date('d.m.Y')
                        ->placeholder('—')
                        ->visible(fn ($record) => filled($record->pilot?->birth_date)),

                    Infolists\Components\TextEntry::make('pilot.pesel')
                        ->label('PESEL pilota')
                        ->placeholder('—')
                        ->visible(fn ($record) => filled($record->pilot?->pesel)),

                    Infolists\Components\TextEntry::make('planned_cost_pln')
                        ->label('Koszt planowany')
                        ->money('PLN')
                        ->visible(fn (?EventSettlement $record): bool => ! $record || ExecutiveAccess::canViewSettlementFinancialSummary($record)),

                    Infolists\Components\TextEntry::make('actual_cost_pln')
                        ->label('Koszt rzeczywisty')
                        ->money('PLN')
                        ->visible(fn (?EventSettlement $record): bool => ! $record || ExecutiveAccess::canViewSettlementFinancialSummary($record)),

                    Infolists\Components\TextEntry::make('cost_diff')
                        ->label('Różnica kosztów')
                        ->state(fn ($record) => $record->actual_cost_pln - $record->planned_cost_pln)
                        ->money('PLN')
                        ->color(fn ($state) => $state > 0 ? 'danger' : ($state < 0 ? 'success' : 'gray'))
                        ->visible(fn (?EventSettlement $record): bool => ! $record || ExecutiveAccess::canViewSettlementFinancialSummary($record)),

                    Infolists\Components\TextEntry::make('participant_balance')
                        ->label('Saldo uczestników')
                        ->state(fn ($record) => $record->participant_paid_pln - $record->participant_due_pln)
                        ->money('PLN')
                        ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                        ->visible(fn (?EventSettlement $record): bool => ! $record || ExecutiveAccess::canViewSettlementFinancialSummary($record)),

                    Infolists\Components\TextEntry::make('net_result_pln')
                        ->label('Wynik netto')
                        ->money('PLN')
                        ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                        ->visible(fn (): bool => ExecutiveAccess::canViewFinalFinancialResults()),

                    Infolists\Components\TextEntry::make('pilot_expenses_planned')
                        ->label('Wydatki pilota – plan')
                        ->state(fn ($record) => round((float) $record->costs()->where('paid_by', 'pilot')->whereNotIn('payment_status', ['cancelled'])->sum('planned_amount_pln'), 2))
                        ->money('PLN'),

                    Infolists\Components\TextEntry::make('pilot_expenses_calculated')
                        ->label('Wydatki pilota – gotówka')
                        ->state(fn ($record) => round((float) $record->pilotCashPreparations()->sum('pln_equivalent'), 2))
                        ->money('PLN'),
                ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            Pages\EditEventSettlement::class,
            Pages\ManageSettlementCosts::class,
            Pages\ManageSettlementPayments::class,
            Pages\ManageSettlementPilotCash::class,
            Pages\ManageSettlementCurrencyExchanges::class,
            Pages\ManageSettlementDocuments::class,
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEventSettlements::route('/'),
            'create' => Pages\CreateEventSettlement::route('/create'),
            'edit' => Pages\EditEventSettlement::route('/{record}/edit'),
            'program-costs' => Pages\ManageSettlementProgramCosts::route('/{record}/program-costs'),
            'costs' => Pages\ManageSettlementCosts::route('/{record}/costs'),
            'payments' => Pages\ManageSettlementPayments::route('/{record}/payments'),
            'pilot-cash' => Pages\ManageSettlementPilotCash::route('/{record}/pilot-cash'),
            'currency-exchanges' => Pages\ManageSettlementCurrencyExchanges::route('/{record}/currency-exchanges'),
            'documents' => Pages\ManageSettlementDocuments::route('/{record}/documents'),
        ];
    }
}
