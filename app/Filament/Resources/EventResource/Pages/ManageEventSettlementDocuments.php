<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventSettlementResource\RelationManagers\DocumentsRelationManager;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Url;

class ManageEventSettlementDocuments extends ManageEventSettlementFinanceSection
{
    protected static string $view = 'filament.resources.event-resource.pages.event-settlement-documents';

    protected static ?string $navigationLabel = 'Dokumenty';

    protected static ?string $title = 'Dokumenty';

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    #[Url(as: 'filter')]
    public string $documentFilter = 'all';

    public function setDocumentFilter(string $filter): void
    {
        if (! array_key_exists($filter, static::documentFilterOptions())) {
            return;
        }

        $this->documentFilter = $filter;
    }

    /**
     * @return array<string, string>
     */
    public static function documentFilterOptions(): array
    {
        $options = [
            'all' => 'Wszystkie',
            'invoices' => 'Faktury',
            'receipt' => 'Paragony',
            'wz' => 'WZ',
            'other' => 'Inne',
        ];

        return $options;
    }

    public function showsVendorInvoices(): bool
    {
        if (! Schema::hasTable('vendor_invoices')) {
            return false;
        }

        return in_array($this->documentFilter, ['all', 'invoices'], true);
    }

    public function showsSettlementDocuments(): bool
    {
        return in_array($this->documentFilter, ['all', 'invoices', 'receipt', 'wz', 'other'], true);
    }

    public function settlementDocumentTypeFilter(): ?string
    {
        return match ($this->documentFilter) {
            'invoices' => 'invoice',
            'receipt' => 'receipt',
            'wz' => 'wz',
            'other' => 'other',
            default => null,
        };
    }

    protected static function settlementRelationManagers(): array
    {
        return [
            DocumentsRelationManager::class,
        ];
    }
}
