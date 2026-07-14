<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\AuthorizesVendorInvoices;
use App\Services\Invoices\VendorInvoiceBatchImportService;
use App\Support\FilamentNavigation;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class VendorInvoiceImportPage extends Page
{
    use AuthorizesVendorInvoices;
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static string $view = 'filament.pages.vendor-invoice-import';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_FINANCE;

    protected static ?string $navigationLabel = 'Import KSeF';

    protected static ?int $navigationSort = 4;

    /** @var TemporaryUploadedFile|null */
    public $csvFile = null;

    /** @var TemporaryUploadedFile|null */
    public $xmlFile = null;

    /** @var TemporaryUploadedFile|null */
    public $pdfFile = null;

    public ?array $lastResult = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && ($user->hasRole(['admin', 'super_admin']) || static::canImportInvoices());
    }

    public function getTitle(): string
    {
        return 'Import faktur KSeF';
    }

    public function runImport(): void
    {
        $this->validate([
            'csvFile' => 'nullable|file|max:20480',
            'xmlFile' => 'nullable|file|max:20480',
            'pdfFile' => 'nullable|file|mimes:pdf|max:102400',
        ], [
            'csvFile.max' => 'Plik CSV jest za duży (max 20 MB).',
            'xmlFile.max' => 'Plik XML jest za duży (max 20 MB).',
            'pdfFile.mimes' => 'Zbiorczy plik musi być w formacie PDF.',
            'pdfFile.max' => 'Plik PDF jest za duży (max 100 MB).',
        ]);

        if (! $this->csvFile && ! $this->xmlFile && ! $this->pdfFile) {
            Notification::make()
                ->title('Wybierz pliki')
                ->body('Dodaj co najmniej jeden plik: CSV, XML lub zbiorczy PDF.')
                ->danger()
                ->send();

            return;
        }

        if ($this->pdfFile && ! $this->csvFile && ! $this->xmlFile) {
            Notification::make()
                ->title('Uwaga')
                ->body('PDF zostanie dopięty tylko do faktur już istniejących w rejestrze (z wcześniejszego importu).')
                ->warning()
                ->send();
        }

        try {
            $this->lastResult = app(VendorInvoiceBatchImportService::class)->import(
                csvPath: $this->resolveTempPath($this->csvFile),
                xmlPath: $this->resolveTempPath($this->xmlFile),
                pdfPath: $this->resolveTempPath($this->pdfFile),
                csvName: $this->csvFile?->getClientOriginalName(),
                xmlName: $this->xmlFile?->getClientOriginalName(),
                pdfName: $this->pdfFile?->getClientOriginalName(),
            );

            $this->reset('csvFile', 'xmlFile', 'pdfFile');

            Notification::make()
                ->title('Import zakończony')
                ->body(sprintf(
                    'Faktury: %d (dopasowane: %d, do opracowania: %d). PDF dopięte: %d.',
                    $this->lastResult['imported'] ?? 0,
                    $this->lastResult['matched'] ?? 0,
                    $this->lastResult['unmatched'] ?? 0,
                    $this->lastResult['pdf_attached'] ?? 0,
                ))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Błąd importu')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getNavigationTabs(): array
    {
        return \App\Support\FinanceModuleNavigation::tabs('import');
    }

    private function resolveTempPath(?TemporaryUploadedFile $file): ?string
    {
        if (! $file) {
            return null;
        }

        $path = $file->getRealPath();

        return ($path && is_file($path)) ? $path : null;
    }
}
