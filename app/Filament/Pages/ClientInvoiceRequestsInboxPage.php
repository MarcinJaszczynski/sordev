<?php

namespace App\Filament\Pages;

use App\Filament\Resources\EventResource;
use App\Models\ClientInvoiceRequest;
use App\Models\Event;
use App\Services\ClientInvoiceRequestWorkflowService;
use App\Support\FilamentNavigation;
use App\Support\FinanceModuleNavigation;
use App\Support\MoneyFormatter;
use App\Support\OperationalListSort;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClientInvoiceRequestsInboxPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.client-invoice-requests-inbox';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_FINANCE;

    protected static ?string $navigationLabel = 'Wnioski o fakturę';

    protected static ?int $navigationSort = 7;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && $user->hasRole(['admin', 'super_admin', 'biuro', 'ksiegowosc']);
    }

    public function getTitle(): string
    {
        return 'Wnioski o fakturę';
    }

    public function getNavigationTabs(): array
    {
        return FinanceModuleNavigation::tabs('client-invoice-requests');
    }

    public function table(Table $table): Table
    {
        return OperationalListSort::applyToTable($table)
            ->query(
                ClientInvoiceRequest::query()
                    ->with(['event:id,code,name', 'contract:id,title,contract_type', 'user:id,name,email', 'processedByUser:id,name'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data wniosku')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (string $state): string => ClientInvoiceRequest::$statuses[$state] ?? $state)
                    ->colors([
                        'warning' => ClientInvoiceRequest::STATUS_PENDING,
                        'success' => ClientInvoiceRequest::STATUS_PROCESSED,
                        'danger' => ClientInvoiceRequest::STATUS_REJECTED,
                    ]),
                Tables\Columns\TextColumn::make('company_name')
                    ->label('Firma / klient')
                    ->searchable()
                    ->description(fn (ClientInvoiceRequest $record): string => 'NIP: '.$record->nip),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Składający')
                    ->description(fn (ClientInvoiceRequest $record): ?string => $record->user?->email)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('event.code')
                    ->label('Impreza')
                    ->description(fn (ClientInvoiceRequest $record): ?string => $record->event?->name)
                    ->url(fn (ClientInvoiceRequest $record): ?string => $record->event_id
                        ? EventResource::getUrl('edit', ['record' => $record->event_id])
                        : null)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'event',
                        fn (Builder $eventQuery): Builder => $eventQuery
                            ->where('code', 'like', '%'.$search.'%')
                            ->orWhere('name', 'like', '%'.$search.'%'),
                    )),
                Tables\Columns\TextColumn::make('contract.title')
                    ->label('Umowa')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Kwota')
                    ->formatStateUsing(fn (?string $state): string => filled($state)
                        ? MoneyFormatter::format((float) $state, 'PLN')
                        : '—'),
                Tables\Columns\TextColumn::make('payment_reference')
                    ->label('Referencja wpłaty')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('invoice_email')
                    ->label('E-mail faktury')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('processedByUser.name')
                    ->label('Obsłużył')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('processed_at')
                    ->label('Data obsługi')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(ClientInvoiceRequest::$statuses)
                    ->default(ClientInvoiceRequest::STATUS_PENDING),
                Tables\Filters\SelectFilter::make('event_id')
                    ->label('Impreza')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => Event::query()
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->limit(20)
                        ->get()
                        ->mapWithKeys(fn (Event $event): array => [$event->id => "{$event->code} — {$event->name}"])
                        ->all())
                    ->getOptionLabelUsing(fn ($value): ?string => optional(Event::find($value), fn (Event $event): string => "{$event->code} — {$event->name}")),
            ])
            ->actions([
                Tables\Actions\Action::make('markProcessed')
                    ->label('Zrealizowany')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ClientInvoiceRequest $record): bool => $record->status === ClientInvoiceRequest::STATUS_PENDING)
                    ->form([
                        Textarea::make('admin_notes')
                            ->label('Notatka wewnętrzna')
                            ->rows(2),
                    ])
                    ->action(function (ClientInvoiceRequest $record, array $data): void {
                        app(ClientInvoiceRequestWorkflowService::class)->markProcessed(
                            $record,
                            auth()->user(),
                            $data['admin_notes'] ?? null,
                        );

                        Notification::make()->title('Wniosek zrealizowany — utworzono szkic FV VAT-Marża')->success()->send();
                    }),
                Tables\Actions\Action::make('markRejected')
                    ->label('Odrzuć')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (ClientInvoiceRequest $record): bool => $record->status === ClientInvoiceRequest::STATUS_PENDING)
                    ->form([
                        Textarea::make('admin_notes')
                            ->label('Powód odrzucenia')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (ClientInvoiceRequest $record, array $data): void {
                        app(ClientInvoiceRequestWorkflowService::class)->markRejected(
                            $record,
                            auth()->user(),
                            $data['admin_notes'] ?? null,
                        );

                        Notification::make()->title('Wniosek odrzucony')->success()->send();
                    }),
                Tables\Actions\Action::make('reopen')
                    ->label('Przywróć do oczekujących')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn (ClientInvoiceRequest $record): bool => $record->status !== ClientInvoiceRequest::STATUS_PENDING)
                    ->requiresConfirmation()
                    ->action(function (ClientInvoiceRequest $record): void {
                        app(ClientInvoiceRequestWorkflowService::class)->reopen($record);

                        Notification::make()->title('Wniosek przywrócony')->success()->send();
                    }),
            ])
            ->bulkActions([]);
    }
}
