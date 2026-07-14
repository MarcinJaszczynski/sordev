<?php

namespace App\Filament\Pages;

use App\Filament\Resources\EventSettlementResource;
use App\Models\Event;
use App\Models\EventSettlementParticipantPayment;
use App\Support\FilamentNavigation;
use App\Support\FinanceModuleNavigation;
use App\Services\ParticipantPaymentBalanceService;
use App\Services\SettlementPaymentHealthService;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ParticipantPaymentsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static string $view = 'filament.pages.participant-payments';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_FINANCE;

    protected static ?string $navigationLabel = 'Wpłaty uczestników';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && ($user->hasRole(['admin', 'super_admin', 'ksiegowosc']) || $user->can('view_any_event::settlement'));
    }

    public function getTitle(): string
    {
        return 'Panel płatności indywidualnych';
    }

    public function getNavigationTabs(): array
    {
        return FinanceModuleNavigation::tabs('participant-payments');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                EventSettlementParticipantPayment::query()
                    ->with(['settlement.event', 'contracts.paymentSchedules', 'agreements'])
            )
            ->defaultSort('updated_at', 'desc')
            ->columns([
                Tables\Columns\BadgeColumn::make('operation_type')
                    ->label('Typ operacji')
                    ->state(fn (): string => 'participant_payment')
                    ->formatStateUsing(fn (): string => 'Wpłata uczestnika')
                    ->color('info'),

                Tables\Columns\TextColumn::make('participant_name')
                    ->label('Uczestnik')
                    ->searchable()
                    ->description(fn (EventSettlementParticipantPayment $record): ?string => $record->booking_reference),

                Tables\Columns\TextColumn::make('settlement.event.code')
                    ->label('Impreza')
                    ->description(fn (EventSettlementParticipantPayment $record): ?string => $record->settlement?->event?->name)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas(
                            'settlement.event',
                            fn (Builder $eventQuery): Builder => $eventQuery
                                ->where('code', 'like', '%'.$search.'%')
                                ->orWhere('name', 'like', '%'.$search.'%'),
                        );
                    }),

                Tables\Columns\TextColumn::make('group_contract')
                    ->label('Umowa grupowa')
                    ->state(function (EventSettlementParticipantPayment $record): string {
                        $contract = $record->contracts->first(fn ($item) => $item->contract_type === 'group')
                            ?? $record->agreements->first(fn ($item) => $item->agreement_type === 'group');

                        return $contract?->contract_number
                            ?? $contract?->agreement_number
                            ?? '—';
                    }),

                Tables\Columns\TextColumn::make('due_amount_pln')
                    ->label('Należne')
                    ->money('PLN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_amount_pln')
                    ->label('Wpłacono')
                    ->money('PLN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('remaining_pln')
                    ->label('Brakuje')
                    ->state(function (EventSettlementParticipantPayment $record): float {
                        $balance = app(ParticipantPaymentBalanceService::class)->balanceRow($record);

                        return (float) ($balance['remaining_pln'] ?? 0);
                    })
                    ->money('PLN')
                    ->color(fn (float $state): string => $state > 0 ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('coverage_status')
                    ->label('Semafor')
                    ->badge()
                    ->state(function (EventSettlementParticipantPayment $record): string {
                        $balance = app(ParticipantPaymentBalanceService::class)->balanceRow($record);

                        return (string) ($balance['coverage_label'] ?? '—');
                    })
                    ->color(function (EventSettlementParticipantPayment $record): string {
                        $balance = app(ParticipantPaymentBalanceService::class)->balanceRow($record);

                        return match ($balance['coverage_status'] ?? '') {
                            SettlementPaymentHealthService::STATUS_OK => 'success',
                            SettlementPaymentHealthService::STATUS_DUE => 'info',
                            SettlementPaymentHealthService::STATUS_OVERDUE => 'warning',
                            default => 'danger',
                        };
                    }),

                Tables\Columns\TextColumn::make('installment_label')
                    ->label('Następna rata')
                    ->state(function (EventSettlementParticipantPayment $record): string {
                        $balance = app(ParticipantPaymentBalanceService::class)->balanceRow($record);

                        return (string) ($balance['installment_label'] ?? '—');
                    })
                    ->placeholder('—'),

                Tables\Columns\BadgeColumn::make('payment_status')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => EventSettlementParticipantPayment::$paymentStatuses[$state] ?? $state)
                    ->colors([
                        'gray' => 'pending',
                        'warning' => 'partial',
                        'success' => 'paid',
                        'info' => 'overpaid',
                        'danger' => 'cancelled',
                    ]),

                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Data wpłaty')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_id')
                    ->label('Impreza')
                    ->options(fn (): array => Event::query()
                        ->whereHas('settlements.participantPayments')
                        ->orderByDesc('start_date')
                        ->limit(200)
                        ->get()
                        ->mapWithKeys(fn (Event $event): array => [$event->id => trim(($event->code ?: '#'.$event->id).' — '.$event->name)])
                        ->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('settlement', fn (Builder $settlement) => $settlement->where('event_id', $data['value']))
                        : $query),

                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('Status')
                    ->options(EventSettlementParticipantPayment::$paymentStatuses),

                Tables\Filters\Filter::make('shortfall_only')
                    ->label('Tylko niedopłaty')
                    ->query(fn (Builder $query): Builder => $query->whereColumn('paid_amount_pln', '<', 'due_amount_pln')),

                Tables\Filters\Filter::make('overdue_only')
                    ->label('Po terminie raty')
                    ->query(function (Builder $query): Builder {
                        $service = app(ParticipantPaymentBalanceService::class);
                        $ids = EventSettlementParticipantPayment::query()
                            ->with(['contracts.paymentSchedules'])
                            ->get()
                            ->filter(function (EventSettlementParticipantPayment $payment) use ($service): bool {
                                $balance = $service->balanceRow($payment);

                                return ($balance['coverage_status'] ?? '') === SettlementPaymentHealthService::STATUS_OVERDUE;
                            })
                            ->pluck('id')
                            ->all();

                        if ($ids === []) {
                            return $query->whereRaw('1 = 0');
                        }

                        return $query->whereIn('id', $ids);
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('open_settlement')
                    ->label('Rozliczenie')
                    ->icon('heroicon-o-calculator')
                    ->url(fn (EventSettlementParticipantPayment $record): ?string => $record->settlement_id
                        ? EventSettlementResource::getUrl('edit', ['record' => $record->settlement_id])
                        : null),
            ]);
    }
}
