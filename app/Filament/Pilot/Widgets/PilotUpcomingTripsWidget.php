<?php

namespace App\Filament\Pilot\Widgets;

use App\Filament\Pilot\Pages\PilotSettlementPage;
use App\Filament\Pilot\Resources\PilotEventResource;
use App\Models\Event;
use App\Services\PilotAccessService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class PilotUpcomingTripsWidget extends BaseWidget
{
    protected static ?string $heading = 'Nadchodzące wycieczki';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $user = Auth::user();

        return $table
            ->query(
                $user
                    ? app(PilotAccessService::class)
                        ->visibleTripsQuery($user)
                        ->whereDate('start_date', '>=', now()->subDays(7))
                        ->orderBy('start_date')
                    : Event::query()->whereRaw('1 = 0')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Impreza')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Start')
                    ->date('d.m.Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Koniec')
                    ->date('d.m.Y'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Teczka')
                    ->icon('heroicon-o-folder-open')
                    ->url(fn (Event $record) => PilotEventResource::getUrl('view', ['record' => $record])),
                Tables\Actions\Action::make('settle')
                    ->label('Rozlicz')
                    ->icon('heroicon-o-calculator')
                    ->url(fn (Event $record) => PilotSettlementPage::settleUrl($record)),
            ])
            ->paginated([5, 10]);
    }
}
