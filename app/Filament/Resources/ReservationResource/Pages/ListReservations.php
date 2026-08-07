<?php

namespace App\Filament\Resources\ReservationResource\Pages;

use App\Filament\Resources\ReservationResource;
use App\Models\Event;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListReservations extends ListRecords
{
    protected static string $resource = ReservationResource::class;

    public function getSubheading(): ?string
    {
        return 'Skrzynka cross-event — rezerwacje w jednej imprezie znajdziesz w Operacjach karty imprezy';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function applySearchToTableQuery(Builder $query): Builder
    {
        $this->applyColumnSearchesToTableQuery($query);

        $search = $this->getTableSearch();

        if (blank($search)) {
            return $query;
        }

        foreach ($this->extractTableSearchWords($search) as $searchWord) {
            ReservationResource::applyTableSearch($query, $searchWord);
        }

        return $query;
    }

    public static function eventFilterLabel(Event $event): string
    {
        $parts = [];

        if (filled($event->code)) {
            $parts[] = $event->code;
        }

        $parts[] = $event->name;

        if ($event->start_date) {
            $parts[] = $event->start_date->format('d.m.Y');
        }

        return implode(' · ', $parts);
    }
}
