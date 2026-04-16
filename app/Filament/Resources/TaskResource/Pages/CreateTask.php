<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTask extends CreateRecord
{
    protected static string $resource = TaskResource::class;

    public function getTitle(): string
    {
        return 'Dodaj zadanie';
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Zadanie zostało utworzone';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['author_id'] = auth()->id();
        return $data;
    }

    /**
     * After creating a task, clear the notification cache for the user
     */
    protected function afterCreate(): void
    {
        parent::afterCreate();
        $userId = auth()->id();
        if ($userId) {
            \App\Services\NotificationService::clearCacheForUser($userId);
        }
    }
}
