<?php

namespace App\Filament\Resources\ContractorResource\RelationManagers;

use App\Filament\Resources\VendorInvoiceResource;
use App\Models\VendorInvoice;
use App\Services\Invoices\ContractorResolver;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class VendorInvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'vendorInvoices';

    protected static ?string $title = 'Faktury';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        $contractor = $this->getOwnerRecord();
        $nip = ContractorResolver::normalizeNip($contractor->nip);

        return $table
            ->query(
                VendorInvoice::query()
                    ->where(function ($q) use ($contractor, $nip) {
                        $q->where('contractor_id', $contractor->id);
                        if ($nip) {
                            $q->orWhere('seller_nip', $nip);
                        }
                    })
            )
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')->label('Numer'),
                Tables\Columns\TextColumn::make('ksef_number')->label('KSeF'),
                Tables\Columns\TextColumn::make('gross_amount')->label('Brutto')->money('PLN'),
                Tables\Columns\TextColumn::make('due_date')->label('Termin')->date('d.m.Y'),
                Tables\Columns\BadgeColumn::make('payment_status')
                    ->label('Płatność')
                    ->formatStateUsing(fn ($state) => VendorInvoice::$paymentStatuses[$state] ?? $state),
                Tables\Columns\TextColumn::make('event.code')->label('Impreza'),
            ])
            ->actions([
                Tables\Actions\Action::make('edit')
                    ->label('Edytuj')
                    ->url(fn (VendorInvoice $record) => VendorInvoiceResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
