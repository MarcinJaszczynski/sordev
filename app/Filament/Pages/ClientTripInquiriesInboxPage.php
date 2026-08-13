<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Crm\AnswerClientTripInquiryAction;
use App\Filament\Resources\EventResource;
use App\Models\ClientTripInquiry;
use App\Support\FilamentNavigation;
use App\Support\OperationalListSort;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class ClientTripInquiriesInboxPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string $view = 'filament.pages.client-trip-inquiries-inbox';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_CONTACTS;

    protected static ?string $navigationLabel = 'Zapytania z portalu';

    protected static ?int $navigationSort = 12;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && $user->hasRole(['admin', 'super_admin', 'biuro']);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Schema::hasTable('client_trip_inquiries');
    }

    public function getTitle(): string
    {
        return 'Zapytania z portalu';
    }

    public static function getNavigationBadge(): ?string
    {
        if (! Schema::hasTable('client_trip_inquiries')) {
            return null;
        }

        $count = ClientTripInquiry::query()
            ->where('status', ClientTripInquiry::STATUS_OPEN)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public function table(Table $table): Table
    {
        return OperationalListSort::applyToTable($table)
            ->query(
                ClientTripInquiry::query()
                    ->with(['event:id,code,name', 'user:id,name,email', 'answeredByUser:id,name'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('source')
                    ->label('Źródło')
                    ->formatStateUsing(fn (?string $state): string => ClientTripInquiry::$sources[$state ?? ClientTripInquiry::SOURCE_CLIENT] ?? (string) $state)
                    ->colors([
                        'info' => ClientTripInquiry::SOURCE_CLIENT,
                        'warning' => ClientTripInquiry::SOURCE_PILOT,
                    ])
                    ->visible(fn (): bool => Schema::hasColumn('client_trip_inquiries', 'source')),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (string $state): string => ClientTripInquiry::$statuses[$state] ?? $state)
                    ->colors([
                        'warning' => ClientTripInquiry::STATUS_OPEN,
                        'success' => ClientTripInquiry::STATUS_ANSWERED,
                        'gray' => ClientTripInquiry::STATUS_CLOSED,
                    ]),
                Tables\Columns\TextColumn::make('subject')
                    ->label('Temat')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Nadawca')
                    ->description(fn (ClientTripInquiry $record): ?string => $record->user?->email),
                Tables\Columns\TextColumn::make('event.code')
                    ->label('Impreza')
                    ->description(fn (ClientTripInquiry $record): ?string => $record->event?->name)
                    ->url(fn (ClientTripInquiry $record): ?string => $record->event_id
                        ? EventResource::getUrl('edit', ['record' => $record->event_id])
                        : null),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('source')
                    ->label('Źródło')
                    ->options(ClientTripInquiry::$sources)
                    ->visible(fn (): bool => Schema::hasColumn('client_trip_inquiries', 'source')),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(ClientTripInquiry::$statuses),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('answer')
                    ->label('Odpowiedz')
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(fn (ClientTripInquiry $record): bool => $record->status === ClientTripInquiry::STATUS_OPEN)
                    ->form([
                        Textarea::make('office_reply')
                            ->label('Odpowiedź dla klienta')
                            ->required()
                            ->rows(5),
                    ])
                    ->action(function (ClientTripInquiry $record, array $data): void {
                        try {
                            app(AnswerClientTripInquiryAction::class)(
                                $record,
                                (string) ($data['office_reply'] ?? ''),
                                auth()->user(),
                            );
                            Notification::make()->title('Odpowiedź wysłana')->success()->send();
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\ViewAction::make()
                    ->form([
                        Textarea::make('body')->label('Treść zapytania')->disabled(),
                        Textarea::make('office_reply')->label('Odpowiedź')->disabled(),
                    ])
                    ->mutateRecordDataUsing(fn (array $data, ClientTripInquiry $record): array => [
                        'body' => $record->body,
                        'office_reply' => $record->office_reply,
                    ]),
            ]);
    }
}
