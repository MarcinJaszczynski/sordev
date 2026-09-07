<?php

namespace App\Filament\Resources\ContractorResource\RelationManagers;

use App\Filament\Resources\LegacyEventResource;
use App\Models\LegacyEvent;
use App\Services\Legacy\LegacyContractorArchiveStats;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class LegacyEventsRelationManager extends RelationManager
{
    protected static string $relationship = 'legacyEvents';

    protected static ?string $title = 'Imprezy archiwalne';

    protected static ?string $modelLabel = 'impreza archiwalna';

    protected static ?string $pluralModelLabel = 'imprezy archiwalne';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->legacyEvents()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return \Illuminate\Support\Facades\Schema::hasTable('legacy_events');
    }

    public function table(Table $table): Table
    {
        $stats = app(LegacyContractorArchiveStats::class)->forContractor($this->getOwnerRecord());
        $description = $stats['total'] > 0
            ? sprintf(
                'Udział: %d (zamawiający: %d, wykonawca: %d) · Zakończone: %d · Anulowane: %d · Okres: %s',
                $stats['total'],
                $stats['as_purchaser'],
                $stats['as_executor'],
                $stats['completed'],
                $stats['cancelled'],
                $stats['years_label'],
            )
            : 'Brak powiązanych imprez archiwalnych.';

        return $table
            ->recordTitleAttribute('name')
            ->description($description)
            ->defaultSort('start_datetime', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('office_id')
                    ->label('Kod')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nazwa')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(50),
                Tables\Columns\TextColumn::make('pivot.role')
                    ->label('Rola')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'purchaser' => 'Zamawiający',
                        'payment' => 'Płatność',
                        'executor' => 'Wykonawca',
                        default => $state ?: '—',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'purchaser' => 'info',
                        'payment' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('pivot.type_name')
                    ->label('Typ')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('client_name')
                    ->label('Klient')
                    ->searchable()
                    ->toggleable()
                    ->wrap()
                    ->limit(40),
                Tables\Columns\TextColumn::make('start_datetime')
                    ->label('Wyjazd')
                    ->date('d.m.Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_datetime')
                    ->label('Powrót')
                    ->date('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('participant_count')
                    ->label('Ucz.')
                    ->alignCenter()
                    ->sortable(),
                Tables\Columns\TextColumn::make('legacy_status')
                    ->label('Status')
                    ->badge()
                    ->sortable()
                    ->color(fn (?string $state): string => LegacyEvent::statusColor($state))
                    ->searchable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('legacy_status')
                    ->label('Status')
                    ->options(fn (): array => LegacyEvent::query()
                        ->whereNotNull('legacy_status')
                        ->whereIn('id', $this->getOwnerRecord()->legacyEvents()->select('legacy_events.id'))
                        ->distinct()
                        ->orderBy('legacy_status')
                        ->pluck('legacy_status', 'legacy_status')
                        ->all()),
                Tables\Filters\SelectFilter::make('role')
                    ->label('Rola')
                    ->options([
                        'purchaser' => 'Zamawiający',
                        'executor' => 'Wykonawca',
                        'payment' => 'Płatność',
                    ])
                    ->query(function ($query, array $data) {
                        if (empty($data['value'])) {
                            return $query;
                        }

                        return $query->wherePivot('role', $data['value']);
                    }),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (LegacyEvent $record): string => LegacyEventResource::getUrl('view', ['record' => $record])),
            ])
            ->bulkActions([])
            ->emptyStateHeading('Brak imprez archiwalnych')
            ->emptyStateDescription('Brak udziału tego kontrahenta w archiwum (jako klient lub wykonawca).');
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
