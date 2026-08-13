<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\AuthorizesVendorInvoices;
use App\Filament\Concerns\InteractsWithVendorInvoiceReview;
use App\Filament\Resources\VendorInvoiceResource;
use App\Models\VendorInvoice;
use App\Services\Invoices\VendorInvoiceBatchImportService;
use App\Support\FilamentNavigation;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class VendorInvoiceImportPage extends Page implements HasTable
{
    use AuthorizesVendorInvoices;
    use InteractsWithTable;
    use InteractsWithVendorInvoiceReview;
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static string $view = 'filament.pages.vendor-invoice-import';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_FINANCE;

    protected static ?string $navigationLabel = 'Import KSeF';

    protected static ?int $navigationSort = 4;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    /** @var TemporaryUploadedFile|null */
    public $csvFile = null;

    /** @var TemporaryUploadedFile|null */
    public $xmlFile = null;

    /** @var TemporaryUploadedFile|null */
    public $pdfFile = null;

    public ?array $lastResult = null;

    /** @var array<int, int> */
    public array $lastBatchIds = [];

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

            $this->lastBatchIds = array_values(array_unique(array_map(
                'intval',
                $this->lastResult['batch_ids'] ?? []
            )));

            $this->reset('csvFile', 'xmlFile', 'pdfFile');
            $this->resetTable();

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

    public function table(Table $table): Table
    {
        return $table
            ->query($this->importedInvoicesQuery())
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Numer')
                    ->searchable()
                    ->description(fn (VendorInvoice $record) => $record->ksef_number),
                Tables\Columns\TextColumn::make('seller_name')
                    ->label('Wystawca')
                    ->limit(32)
                    ->description(fn (VendorInvoice $record) => $record->seller_nip ? 'NIP '.$record->seller_nip : null)
                    ->searchable(),
                Tables\Columns\TextColumn::make('gross_amount')
                    ->label('Brutto')
                    ->money('PLN')
                    ->sortable(),
                Tables\Columns\TextColumn::make('lines_count')
                    ->counts('lines')
                    ->label('Poz.')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('sale_date')
                    ->label('Sprzedaż')
                    ->date('d.m.Y')
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('payment_status')
                    ->label('Płatność')
                    ->formatStateUsing(fn ($state) => VendorInvoice::$paymentStatuses[$state] ?? $state)
                    ->colors([
                        'warning' => 'due',
                        'success' => 'paid',
                        'info' => 'partial',
                        'danger' => 'cancelled',
                    ]),
                Tables\Columns\BadgeColumn::make('matching_status')
                    ->label('Dopasowanie')
                    ->formatStateUsing(fn ($state) => VendorInvoice::$matchingStatuses[$state] ?? $state)
                    ->colors([
                        'success' => 'auto_matched',
                        'info' => 'manual',
                        'warning' => 'needs_review',
                        'gray' => 'unmatched',
                    ]),
                Tables\Columns\TextColumn::make('event.code')
                    ->label('Impreza')
                    ->placeholder('—')
                    ->description(fn (VendorInvoice $record) => $record->event?->name),
                Tables\Columns\TextColumn::make('contractor.name')
                    ->label('Kontrahent')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('pdf_path')
                    ->label('PDF')
                    ->boolean()
                    ->trueIcon('heroicon-o-document-check')
                    ->falseIcon('heroicon-o-document'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('matching_status')
                    ->label('Dopasowanie')
                    ->options(VendorInvoice::$matchingStatuses),
                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('Płatność')
                    ->options(VendorInvoice::$paymentStatuses),
            ])
            ->actions($this->vendorInvoiceReviewActions())
            ->emptyStateHeading($this->lastBatchIds === []
                ? 'Brak wyników importu'
                : 'Brak faktur w tym batchu')
            ->emptyStateDescription($this->lastBatchIds === []
                ? 'Po wczytaniu CSV/XML pojawi się tu lista faktur do podglądu i przypisania.'
                : 'Batch nie zawiera faktur albo zostały usunięte.')
            ->paginated([10, 25, 50]);
    }

    public function getInboxUrl(): string
    {
        return VendorInvoiceInboxPage::getUrl();
    }

    public function getRegistryUrl(): string
    {
        return VendorInvoiceResource::getUrl('index');
    }

    public function getNavigationTabs(): array
    {
        return \App\Support\FinanceModuleNavigation::tabs('import');
    }

    /**
     * @return Builder<VendorInvoice>
     */
    private function importedInvoicesQuery(): Builder
    {
        $query = VendorInvoice::query()->with(['event', 'contractor', 'lines']);

        if ($this->lastBatchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('import_batch_id', $this->lastBatchIds);
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
