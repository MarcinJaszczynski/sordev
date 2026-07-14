<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use App\Models\Task;
use App\Support\Tasks\TaskAttachmentStore;
use Filament\Resources\Pages\CreateRecord;

class CreateTask extends CreateRecord
{
    protected static string $resource = TaskResource::class;

    /** @var array<int, string|null> */
    protected array $pendingAttachments = [];

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
        $this->pendingAttachments = $data['pending_attachments'] ?? [];
        unset($data['pending_attachments']);
        $data['author_id'] = auth()->id();

        return $data;
    }

    /**
     * After creating a task, clear the notification cache for the user
     */
    protected function afterCreate(): void
    {
        TaskAttachmentStore::storeMany($this->record, $this->pendingAttachments, auth()->id());

        $userId = auth()->id();
        if ($userId) {
            \App\Services\NotificationService::clearCacheForUser($userId);
        }

        $this->dispatch('refresh-notifications');
    }
}
