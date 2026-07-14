<?php

namespace App\Filament\Resources\VendorInvoiceResource\Pages;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\VendorInvoiceResource;
use App\Services\Invoices\VendorInvoiceProgramPointSync;
use App\Services\Invoices\VendorInvoiceSettlementSync;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditVendorInvoice extends EditRecord
{
    protected static string $resource = VendorInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('open_event')
                ->label(fn () => $this->record->event
                    ? 'Impreza: '.$this->record->event->code
                    : 'Przejdź do imprezy')
                ->icon('heroicon-o-calendar-days')
                ->url(fn () => EventResource::getUrl('edit', ['record' => $this->record->event_id]))
                ->visible(fn () => (bool) $this->record->event_id),
            ...parent::getHeaderActions(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['approval_status'] ?? '') === 'approved' && $this->record->approval_status !== 'approved') {
            $data['approved_by'] = Auth::id();
            $data['approved_at'] = now();
        }

        if (($data['event_id'] ?? null) && ($data['matching_status'] ?? '') === 'unmatched') {
            $data['matching_status'] = 'manual';
        }

        if (($data['payment_status'] ?? '') === 'paid') {
            $data['paid_amount'] = $data['paid_amount'] ?? $data['gross_amount'] ?? $this->record->gross_amount;
            $data['payment_date'] = $data['payment_date'] ?? now()->toDateString();
        }

        if (($data['payment_status'] ?? '') === 'due') {
            $data['payment_date'] = null;
            $data['paid_amount'] = 0;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $fresh = $this->record->fresh();

        if ($fresh->approval_status === 'approved' && $fresh->sync_to_settlement && ! $fresh->settlement_document_id) {
            app(VendorInvoiceSettlementSync::class)->sync($fresh);
        }

        if ($fresh->event_program_point_id) {
            app(VendorInvoiceProgramPointSync::class)->sync($fresh);
        }
    }
}
