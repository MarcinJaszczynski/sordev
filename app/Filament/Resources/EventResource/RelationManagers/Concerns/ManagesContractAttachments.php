<?php

namespace App\Filament\Resources\EventResource\RelationManagers\Concerns;

use App\Services\ContractAttachmentCatalogService;

trait ManagesContractAttachments
{
    protected function attachmentCatalogService(): ContractAttachmentCatalogService
    {
        return app(ContractAttachmentCatalogService::class);
    }

    protected function getSelectableAttachmentOptions(): array
    {
        return $this->attachmentCatalogService()->getOptions();
    }

    /**
     * @param  array<int, string>|null  $existingAttachments
     * @return array<int, string>
     */
    protected function resolveSelectedAttachmentDefaults(?array $existingAttachments = null, ?int $contractTemplateId = null): array
    {
        return $this->attachmentCatalogService()->resolveDefaultSelectedPaths($contractTemplateId, $existingAttachments);
    }

    protected function resolveAttachmentsFromData(array $data): array
    {
        return $this->attachmentCatalogService()->resolveAttachmentsFromFormData($data);
    }

    protected function mergeSelectedAttachmentsIntoData(array $data): array
    {
        return $this->attachmentCatalogService()->mergeSelectedAttachmentsIntoFormData($data);
    }
}
