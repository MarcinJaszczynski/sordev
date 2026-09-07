<?php

namespace App\Filament\Resources\LegacyEventResource\Pages;

use App\Filament\Resources\LegacyEventResource;
use App\Models\LegacyEvent;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListLegacyEvents extends ListRecords
{
    protected static string $resource = LegacyEventResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Wszystkie')
                ->badge(fn (): int => LegacyEvent::query()->count()),
            'completed' => Tab::make('Zakończone')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('legacy_status', 'Zakończona'))
                ->badge(fn (): int => LegacyEvent::query()->where('legacy_status', 'Zakończona')->count())
                ->badgeColor('success'),
            'cancelled' => Tab::make('Anulowane')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('legacy_status', 'Anulowana'))
                ->badge(fn (): int => LegacyEvent::query()->where('legacy_status', 'Anulowana')->count())
                ->badgeColor('danger'),
            'active' => Tab::make('Aktywne / oferty')
                ->modifyQueryUsing(fn (Builder $query) => $query->where(function (Builder $q): void {
                    $q->whereNotIn('legacy_status', ['Zakończona', 'Anulowana', 'Archiwum'])
                        ->orWhereNull('legacy_status');
                }))
                ->badge(fn (): int => LegacyEvent::query()
                    ->where(function (Builder $q): void {
                        $q->whereNotIn('legacy_status', ['Zakończona', 'Anulowana', 'Archiwum'])
                            ->orWhereNull('legacy_status');
                    })
                    ->count())
                ->badgeColor('warning'),
            'unlinked' => Tab::make('Bez kontrahenta')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('contractor_id'))
                ->badge(fn (): int => LegacyEvent::query()->whereNull('contractor_id')->count())
                ->badgeColor('gray'),
        ];
    }
}
