<?php

namespace App\Filament\Pages;

use App\Support\FilamentNavigation;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class ImportExportPanel extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static string $view = 'filament.pages.import-export-panel';

    protected static ?string $navigationLabel = 'Import / eksport danych';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_SYSTEM;

    public $models = [
        'Event' => \App\Models\Event::class,
        'Contractor' => \App\Models\Contractor::class,
        'Contact' => \App\Models\Contact::class,
        'Bus' => \App\Models\Bus::class,
        'HotelRoom' => \App\Models\HotelRoom::class,
        'Payer' => \App\Models\Payer::class,
    ];

    public $selectedModel = null;

    public $importFile = null;

    public $importResult = null;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return (bool) $user?->hasRole(['admin', 'super_admin']);
    }

    public function mount()
    {
        abort_unless(static::canAccess(), 403);

        $this->selectedModel = array_key_first($this->models);
    }

    public function updatedImportFile($value)
    {
        $this->importFile = $value;
    }

    public function import()
    {
        abort_unless(static::canAccess(), 403);

        $modelClass = $this->models[$this->selectedModel] ?? null;
        if (! $modelClass || ! $this->importFile) {
            $this->importResult = 'Wybierz model i plik CSV.';

            return;
        }
        try {
            Excel::import(new \App\Imports\GenericImport($modelClass), $this->importFile);
            $this->importResult = 'Import zakończony!';
        } catch (\Exception $e) {
            $this->importResult = 'Błąd importu: '.$e->getMessage();
        }
    }

    public function export()
    {
        abort_unless(static::canAccess(), 403);

        $modelClass = $this->models[$this->selectedModel] ?? null;
        if (! $modelClass) {
            return null;
        }
        $exportClass = '\App\Exports\GenericExport';

        return Excel::download(new $exportClass($modelClass), strtolower($this->selectedModel).'.csv');
    }
}
