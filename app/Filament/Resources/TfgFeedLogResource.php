<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresUfgContractsTable;
use App\Filament\Resources\TfgFeedLogResource\Pages;
use App\Models\TfgFeedLog;
use App\Support\FilamentNavigation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class TfgFeedLogResource extends Resource
{
    use RequiresUfgContractsTable;

    protected static ?string $model = TfgFeedLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationLabel = 'Logi TFG';

    protected static ?string $modelLabel = 'Log feedu';

    protected static ?string $pluralModelLabel = 'Logi feedów TFG';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_FINANCE;

    protected static ?int $navigationSort = 11;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('feed_identifier')->label('Identyfikator feedu')->disabled(),
            Forms\Components\TextInput::make('operation_type')->label('Operacja')->disabled(),
            Forms\Components\TextInput::make('sync_status')->label('Status sync')->disabled(),
            Forms\Components\TextInput::make('async_status')->label('Status async')->disabled(),
            Forms\Components\Textarea::make('sync_errors_json')
                ->label('Błędy sync')
                ->formatStateUsing(fn (?TfgFeedLog $record) => json_encode($record?->sync_errors, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                ->disabled()
                ->rows(6),
            Forms\Components\Textarea::make('async_errors_json')
                ->label('Błędy async')
                ->formatStateUsing(fn (?TfgFeedLog $record) => json_encode($record?->async_errors, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                ->disabled()
                ->rows(6),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('feed_identifier')->label('Feed')->searchable(),
                Tables\Columns\TextColumn::make('operation_type')->label('Operacja'),
                Tables\Columns\TextColumn::make('contracts_count')->label('Umowy'),
                Tables\Columns\TextColumn::make('sync_status')->label('Sync')->badge(),
                Tables\Columns\TextColumn::make('async_status')->label('Async')->badge(),
                Tables\Columns\TextColumn::make('submitted_at')->label('Wysłano')->dateTime('d.m.Y H:i'),
                Tables\Columns\TextColumn::make('completed_at')->label('Zakończono')->dateTime('d.m.Y H:i'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('download_payload')
                    ->label('Pobierz JSON')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (TfgFeedLog $record) => filled($record->payload_path) && Storage::disk('local')->exists($record->payload_path))
                    ->url(fn (TfgFeedLog $record) => route('tfg.feed-log.download', $record))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('submitted_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTfgFeedLogs::route('/'),
            'view' => Pages\ViewTfgFeedLog::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
