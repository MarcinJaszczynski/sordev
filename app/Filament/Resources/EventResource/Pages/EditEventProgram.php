<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use Filament\Resources\Pages\EditRecord;

class EditEventProgram extends EditRecord
{
    protected static string $resource = EventResource::class;
    
    public function mount($record): void
    {
        parent::mount($record);
        
        // Przekieruj na stronę edycji z zakładką programu
        $this->redirect(EventResource::getUrl('edit', ['record' => $record]) . '#program-points');
    }
}
