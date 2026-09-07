<?php

namespace App\Filament\Resources\EventTemplateResource\Pages;

use App\Filament\Concerns\AuthorizesEventTemplatePages;
use App\Filament\Concerns\ConfirmsEventTemplateEditing;
use App\Filament\Concerns\SingleRelationManagerPage;
use App\Filament\Resources\EventTemplateResource;
use App\Filament\Resources\EventTemplateResource\Concerns\HasEventTemplateWorkflowContext;
use App\Filament\Resources\EventTemplateResource\RelationManagers\QtyVariantsRelationManager;

class ManageTemplateQtyVariants extends SingleRelationManagerPage
{
    use AuthorizesEventTemplatePages;
    use ConfirmsEventTemplateEditing;
    use HasEventTemplateWorkflowContext;

    protected static string $resource = EventTemplateResource::class;

    protected static ?string $navigationLabel = 'Warianty ilości';

    protected static ?string $title = 'Warianty ilości uczestników';

    protected static ?string $navigationIcon = 'heroicon-o-users';

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->bootTemplateEditingGate();
    }

    protected function getHeaderActions(): array
    {
        return $this->templateEditingHeaderActions();
    }

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return 'Warianty ilości uczestników';
    }

    public function getSubheading(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        return 'Kolumny kalkulacji dla różnych wielkości grup (uczestnicy + gratis/kadra/kierowca). Lista pochodzi z globalnego katalogu wariantów ilości.';
    }

    protected static function relationManager(): string
    {
        return QtyVariantsRelationManager::class;
    }
}
