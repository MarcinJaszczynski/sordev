<?php

namespace App\Filament\Resources\ReservationResource\Pages;

use App\Filament\Forms\ReservationFormFields;
use App\Filament\Resources\ReservationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReservation extends CreateRecord
{
    protected static string $resource = ReservationResource::class;

    /** @var array<int, string> */
    protected array $pendingAttachments = [];

    public function mount(): void
    {
        parent::mount();

        $fill = [];

        if ($eventId = request()->integer('event_id')) {
            $fill['event_id'] = $eventId;
        }

        if ($programPointId = request()->integer('program_point_id')) {
            $fill['program_point_id'] = $programPointId;
        }

        if ($settlementId = request()->integer('settlement_id')) {
            $fill['settlement_id'] = $settlementId;
        }

        if ($fill !== []) {
            $this->form->fill($fill);
        }
    }

    /** @param array<string, mixed> $data */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingAttachments = $data['pending_attachments'] ?? [];

        return ReservationFormFields::normalizeSaveData($data);
    }

    protected function afterCreate(): void
    {
        ReservationFormFields::persistAttachments($this->getRecord(), [
            'pending_attachments' => $this->pendingAttachments,
        ]);
    }
}
