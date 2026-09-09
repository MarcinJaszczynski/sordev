<?php
// app/Filament/Resources/TaskResource.php
// Fragment metody table() — czytelna lista zadań z kolorowymi statusami
// i priorytetem jako ikoną, zamiast gęstego tekstu jak w obecnym widoku.

use Filament\Tables;
use Filament\Tables\Table;

public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\TextColumn::make('title')
                ->label('Zadanie')
                ->weight('semibold')
                ->searchable()
                ->description(fn ($record) => $record->event?->name
                    ? "{$record->event->name} · #{$record->event->id}"
                    : 'Wolne / nieprzypisane'),

            Tables\Columns\TextColumn::make('assignee.name')
                ->label('Przypisane do')
                ->badge()
                ->color('gray'),

            Tables\Columns\TextColumn::make('status')
                ->label('Status')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'todo' => 'warning',
                    'in_progress' => 'info',
                    'done' => 'success',
                    'cancelled' => 'danger',
                    default => 'gray',
                })
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'todo' => 'Do zrobienia',
                    'in_progress' => 'W trakcie',
                    'done' => 'Zrobione',
                    'cancelled' => 'Anulowano',
                    default => $state,
                }),

            Tables\Columns\IconColumn::make('priority')
                ->label('Priorytet')
                ->icon(fn (string $state): string => $state === 'urgent'
                    ? 'heroicon-s-exclamation-circle'
                    : 'heroicon-o-minus-circle')
                ->color(fn (string $state): string => $state === 'urgent' ? 'danger' : 'gray')
                ->tooltip(fn (string $state): string => $state === 'urgent' ? 'Pilny' : 'Zwykły'),

            Tables\Columns\TextColumn::make('due_at')
                ->label('Termin')
                ->dateTime('d.m.Y, H:i')
                ->sortable()
                ->color(fn ($record) => $record->due_at?->isPast() && $record->status !== 'done'
                    ? 'danger'
                    : null),
        ])
        ->defaultSort('due_at')
        ->filters([
            Tables\Filters\SelectFilter::make('status')
                ->label('Status')
                ->options([
                    'todo' => 'Do zrobienia',
                    'in_progress' => 'W trakcie',
                    'done' => 'Zrobione',
                    'cancelled' => 'Anulowano',
                ]),
        ])
        ->recordUrl(fn ($record) => static::getUrl('view', ['record' => $record]));
}
