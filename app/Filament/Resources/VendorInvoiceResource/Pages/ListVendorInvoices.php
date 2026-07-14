<?php

namespace App\Filament\Resources\VendorInvoiceResource\Pages;

use App\Filament\Resources\VendorInvoiceResource;
use App\Filament\Widgets\FinanceModuleNavWidget;
use Filament\Resources\Pages\ListRecords;

class ListVendorInvoices extends ListRecords
{
    protected static string $resource = VendorInvoiceResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            FinanceModuleNavWidget::make(['activeTab' => 'invoices']),
        ];
    }

    public function getSubheading(): ?string
    {
        return 'Pełny rejestr faktur kosztowych — filtruj po płatności, akceptacji i dopasowaniu do imprezy.';
    }
}
