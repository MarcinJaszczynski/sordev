<?php

namespace App\Filament\Pages;

use App\Filament\Forms\ClientInvoiceRequestFormFields;
use App\Filament\Resources\EventResource;
use App\Models\ClientInvoiceRequest;
use App\Models\Event;
use App\Services\ClientInvoiceRequestWorkflowService;
use App\Support\ClientInvoiceRequestAdminHelper;
use App\Support\FilamentNavigation;
use App\Support\FinanceModuleNavigation;
use App\Support\MoneyFormatter;
use App\Support\OperationalListSort;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class ClientInvoiceRequestsInboxPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.client-invoice-requests-inbox';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_FINANCE;

    protected static ?string $navigationLabel = 'Wnioski o fakturę';

    protected static ?int $navigationSort = 7;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

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

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create_manual_request')
                ->label('Nowy wniosek (ręcznie)')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->modalHeading('Wniosek o fakturę — z palca')
                ->modalDescription('Formularz dla biura — wybierz imprezę i dane nabywcy.')
                ->modalIcon('heroicon-o-receipt-percent')
                ->modalWidth('3xl')
                ->modalSubmitActionLabel('Zapisz wniosek')
                ->form(ClientInvoiceRequestFormFields::adminModalSchema())
                ->action(function (array $data): void {
                    ClientInvoiceRequestAdminHelper::createFromAdminForm($data);

                    Notification::make()
                        ->title('Utworzono wniosek o fakturę')
                        ->success()
                        ->send();
                }),
        ];
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
                Tables\Columns\BadgeColumn::make('source')
                    ->label('Źródło')
                    ->formatStateUsing(fn (?string $state): string => ClientInvoiceRequest::$sources[$state] ?? ($state ?: '—'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('company_name')
                    ->label('Nabywca')
                    ->searchable()
                    ->description(fn (ClientInvoiceRequest $record): string => trim(
                        ($record->buyer_type_label)
                        .($record->nip ? ' · NIP: '.$record->nip : '')
                    )),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Składający')
                    ->description(fn (ClientInvoiceRequest $record): ?string => $record->user?->email ?? $record->invoice_email)
                    ->placeholder('WWW'),
                Tables\Columns\TextColumn::make('event.code')
                    ->label('Impreza')
                    ->placeholder(fn (ClientInvoiceRequest $record): string => $record->event_code_entered
                        ? 'Kod: '.$record->event_code_entered.' (niepowiązana)'
                        : '— niepowiązana —')
                    ->description(fn (ClientInvoiceRequest $record): ?string => $record->event?->name)
                    ->url(fn (ClientInvoiceRequest $record): ?string => $record->event_id
                        ? EventResource::getUrl('edit', ['record' => $record->event_id])
                        : null)
                    ->color(fn (ClientInvoiceRequest $record): ?string => $record->event_id ? null : 'danger')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(function (Builder $q) use ($search): void {
                        $q->where('event_code_entered', 'like', '%'.$search.'%')
                            ->orWhereHas(
                                'event',
                                fn (Builder $eventQuery): Builder => $eventQuery
                                    ->where('code', 'like', '%'.$search.'%')
                                    ->orWhere('name', 'like', '%'.$search.'%'),
                            );
                    })),
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
                Tables\Filters\SelectFilter::make('source')
                    ->label('Źródło')
                    ->options(ClientInvoiceRequest::$sources),
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
                Tables\Filters\TernaryFilter::make('unlinked')
                    ->label('Bez imprezy')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNull('event_id'),
                        false: fn (Builder $query): Builder => $query->whereNotNull('event_id'),
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('attachEvent')
                    ->label('Powiąż z imprezą')
                    ->icon('heroicon-o-link')
                    ->color('gray')
                    ->visible(fn (ClientInvoiceRequest $record): bool => $record->status === ClientInvoiceRequest::STATUS_PENDING)
                    ->form([
                        Select::make('event_id')
                            ->label('Impreza')
                            ->required()
                            ->searchable()
                            ->default(fn (ClientInvoiceRequest $record): ?int => $record->event_id)
                            ->getSearchResultsUsing(fn (string $search): array => Event::query()
                                ->where('code', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%")
                                ->limit(30)
                                ->get()
                                ->mapWithKeys(fn (Event $event): array => [$event->id => "{$event->code} — {$event->name}"])
                                ->all())
                            ->getOptionLabelUsing(fn ($value): ?string => optional(Event::find($value), fn (Event $event): string => "{$event->code} — {$event->name}")),
                    ])
                    ->action(function (ClientInvoiceRequest $record, array $data): void {
                        $event = Event::query()->findOrFail($data['event_id']);
                        app(ClientInvoiceRequestWorkflowService::class)->attachEvent($record, $event);

                        Notification::make()->title('Wniosek powiązany z imprezą '.$event->code)->success()->send();
                    }),
                Tables\Actions\Action::make('markProcessed')
                    ->label('Zrealizowany')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ClientInvoiceRequest $record): bool => $record->status === ClientInvoiceRequest::STATUS_PENDING)
                    ->disabled(fn (ClientInvoiceRequest $record): bool => ! $record->event_id)
                    ->tooltip(fn (ClientInvoiceRequest $record): ?string => $record->event_id
                        ? null
                        : 'Najpierw powiąż wniosek z imprezą')
                    ->form([
                        Textarea::make('admin_notes')
                            ->label('Notatka wewnętrzna')
                            ->rows(2),
                    ])
                    ->action(function (ClientInvoiceRequest $record, array $data): void {
                        try {
                            app(ClientInvoiceRequestWorkflowService::class)->markProcessed(
                                $record,
                                auth()->user(),
                                $data['admin_notes'] ?? null,
                            );
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Wniosek zrealizowany — szkic FV + próba Fakturowni')->success()->send();
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
