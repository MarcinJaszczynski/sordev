<?php

namespace App\Filament\Resources\EventTemplateResource\Pages;

use App\Filament\Concerns\AuthorizesEventTemplatePages;
use App\Filament\Concerns\SingleRelationManagerPage;
use App\Filament\Resources\EventTemplateResource;
use App\Filament\Resources\EventTemplateResource\Concerns\HasEventTemplateWorkflowContext;
use App\Filament\Resources\EventTemplateResource\RelationManagers\QtyVariantsRelationManager;

class ManageTemplateQtyVariants extends SingleRelationManagerPage
{
    use AuthorizesEventTemplatePages;
    use HasEventTemplateWorkflowContext;

    protected static string $resource = EventTemplateResource::class;

    protected static ?string $navigationLabel = 'Warianty cen';

    protected static ?string $title = 'Warianty ilości uczestników';

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static function relationManager(): string
    {
        return QtyVariantsRelationManager::class;
    }
}
