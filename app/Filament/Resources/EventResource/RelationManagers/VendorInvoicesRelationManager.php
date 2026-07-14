<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Filament\Resources\VendorInvoiceResource;
use App\Models\VendorInvoice;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class VendorInvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'vendorInvoices';

    protected static ?string $title = 'Faktury KSeF';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')->label('Numer'),
                Tables\Columns\TextColumn::make('seller_name')->label('Wystawca')->limit(30),
                Tables\Columns\TextColumn::make('gross_amount')->label('Brutto')->money('PLN'),
                Tables\Columns\TextColumn::make('due_date')->label('Termin')->date('d.m.Y'),
                Tables\Columns\BadgeColumn::make('payment_status')
                    ->label('Płatność')
                    ->formatStateUsing(fn ($state) => VendorInvoice::$paymentStatuses[$state] ?? $state),
                Tables\Columns\BadgeColumn::make('approval_status')
                    ->label('Akceptacja')
                    ->formatStateUsing(fn ($state) => VendorInvoice::$approvalStatuses[$state] ?? $state),
            ])
            ->actions([
                Tables\Actions\Action::make('edit')
                    ->label('Szczegóły')
                    ->url(fn (VendorInvoice $record) => VendorInvoiceResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
