<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\EventProgramPoint;
use App\Models\EventSettlementCost;
use App\Models\Reservation;

readonly class UpsertReservationData
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $attachmentData  Raw form data (may include pending_attachments).
     */
    public function __construct(
        public array $attributes,
        public ?Reservation $reservation = null,
        public ?EventProgramPoint $programPoint = null,
        public ?EventSettlementCost $settlementCost = null,
        public array $attachmentData = [],
        public ?int $createdBy = null,
    ) {}

    /**
     * Mapowanie typowego formularza Filament → DTO (jedna ścieżka dla RelationManagerów / Resource).
     *
     * @param  array<string, mixed>  $formData
     * @param  array<string, mixed>  $attributeOverrides
     * @param  array<string, mixed>|null  $attachmentData
     */
    public static function fromForm(
        array $formData,
        ?Reservation $reservation = null,
        ?EventProgramPoint $programPoint = null,
        ?EventSettlementCost $settlementCost = null,
        ?array $attachmentData = null,
        ?int $createdBy = null,
        array $attributeOverrides = [],
    ): self {
        return new self(
            attributes: [...$formData, ...$attributeOverrides],
            reservation: $reservation,
            programPoint: $programPoint,
            settlementCost: $settlementCost,
            attachmentData: $attachmentData ?? $formData,
            createdBy: $createdBy ?? auth()->id(),
        );
    }
}
