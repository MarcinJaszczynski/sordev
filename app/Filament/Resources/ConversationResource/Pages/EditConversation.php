<?php

namespace App\Filament\Resources\ConversationResource\Pages;

use App\Filament\Resources\ConversationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditConversation extends EditRecord
{
    protected static string $resource = ConversationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Załaduj uczestników dla formularza
        $data['participants'] = $this->record->participants->pluck('id')->toArray();
        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Pobierz uczestników przed aktualizacją
        $participants = $data['participants'] ?? [];
        unset($data['participants']);

        // Aktualizuj rozmowę
        $record->update($data);

        // Przygotuj listę uczestników - zawsze uwzględnij twórcę
        $allParticipants = collect($participants);
        if (!$allParticipants->contains($record->created_by)) {
            $allParticipants->push($record->created_by);
        }

        // Zsynchronizuj uczestników (automatycznie usuwa starych i dodaje nowych)
        $participantsData = [];
        foreach ($allParticipants->unique() as $userId) {
            $participantsData[$userId] = [
                'joined_at' => $record->participants()->where('user_id', $userId)->exists() 
                    ? $record->participants()->where('user_id', $userId)->first()->pivot->joined_at 
                    : now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        
        $record->participants()->sync($participantsData);

        return $record;
    }
}
