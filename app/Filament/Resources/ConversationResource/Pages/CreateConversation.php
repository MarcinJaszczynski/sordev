<?php

namespace App\Filament\Resources\ConversationResource\Pages;

use App\Filament\Resources\ConversationResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateConversation extends CreateRecord
{
    protected static string $resource = ConversationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();
        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        // Pobierz uczestników przed utworzeniem rozmowy
        $participants = $data['participants'] ?? [];
        unset($data['participants']);

        // Utwórz rozmowę
        $record = static::getModel()::create($data);

        // Zbierz wszystkich unikalnych uczestników (włączając twórcę)
        $allParticipants = collect($participants);
        $creatorId = Auth::id();
        
        // Dodaj twórcę jeśli nie jest już na liście
        if (!$allParticipants->contains($creatorId)) {
            $allParticipants->push($creatorId);
        }

        // Użyj relacji Eloquent do dodania uczestników (automatycznie unika duplikatów)
        $participantsData = [];
        foreach ($allParticipants->unique() as $userId) {
            $participantsData[$userId] = [
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        
        $record->participants()->attach($participantsData);

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
