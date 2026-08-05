<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesInvoiceResource\Pages;

use App\Filament\Resources\SalesInvoiceResource;
use App\Models\SalesInvoice;
use App\Support\MoneyFormatter;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewSalesInvoice extends ViewRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('markIssuedLocal')
                ->label('Wystaw lokalnie')
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn (): bool => $this->record->status === SalesInvoice::STATUS_DRAFT)
                ->requiresConfirmation()
                ->action(function (): void {
                    /** @var SalesInvoice $record */
                    $record = $this->record;
                    $record->update([
                        'status' => SalesInvoice::STATUS_ISSUED_LOCAL,
                        'number' => $record->number ?: ('LOK-'.now()->format('Ymd').'-'.$record->id),
                    ]);
                    $this->refreshFormData(['status', 'number']);
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Nagłówek')->columns(3)->schema([
                Infolists\Components\TextEntry::make('id')->label('ID'),
                Infolists\Components\TextEntry::make('number')->label('Numer')->placeholder('—'),
                Infolists\Components\TextEntry::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (?string $state): string => SalesInvoice::$statuses[$state] ?? (string) $state),
                Infolists\Components\TextEntry::make('type')
                    ->label('Typ')
                    ->formatStateUsing(fn (?string $state): string => SalesInvoice::$types[$state] ?? (string) $state),
                Infolists\Components\TextEntry::make('procedure')->label('Procedura'),
                Infolists\Components\TextEntry::make('ksef_number')->label('KSeF')->placeholder('— (bez wysyłki)'),
                Infolists\Components\TextEntry::make('event.name')
                    ->label('Impreza')
                    ->formatStateUsing(fn ($state, SalesInvoice $record): string => trim(($record->event?->code ? $record->event->code.' — ' : '').($state ?? ''))),
            ]),
            Infolists\Components\Section::make('Nabywca')->columns(2)->schema([
                Infolists\Components\TextEntry::make('buyer_name')->label('Nazwa'),
                Infolists\Components\TextEntry::make('buyer_nip')->label('NIP'),
                Infolists\Components\TextEntry::make('buyer_address')->label('Adres')->columnSpanFull(),
            ]),
            Infolists\Components\Section::make('VAT-Marża')->columns(3)->schema([
                Infolists\Components\TextEntry::make('revenue_pln')
                    ->label('Przychód')
                    ->formatStateUsing(fn ($state): string => MoneyFormatter::format((float) $state, 'PLN')),
                Infolists\Components\TextEntry::make('cost_pln')
                    ->label('Koszt')
                    ->formatStateUsing(fn ($state): string => MoneyFormatter::format((float) $state, 'PLN')),
                Infolists\Components\TextEntry::make('margin_gross_pln')
                    ->label('Marża brutto')
                    ->formatStateUsing(fn ($state): string => MoneyFormatter::format((float) $state, 'PLN')),
                Infolists\Components\TextEntry::make('margin_net_pln')
                    ->label('Marża netto')
                    ->formatStateUsing(fn ($state): string => MoneyFormatter::format((float) $state, 'PLN')),
                Infolists\Components\TextEntry::make('vat_on_margin_pln')
                    ->label('VAT od marży')
                    ->formatStateUsing(fn ($state): string => MoneyFormatter::format((float) $state, 'PLN')),
            ]),
            Infolists\Components\RepeatableEntry::make('lines')
                ->label('Pozycje')
                ->schema([
                    Infolists\Components\TextEntry::make('name')->label('Nazwa'),
                    Infolists\Components\TextEntry::make('quantity')->label('Ilość'),
                    Infolists\Components\TextEntry::make('total_pln')
                        ->label('Wartość')
                        ->formatStateUsing(fn ($state): string => MoneyFormatter::format((float) $state, 'PLN')),
                ]),
            Infolists\Components\TextEntry::make('notes')->label('Notatki')->columnSpanFull(),
        ]);
    }
}
