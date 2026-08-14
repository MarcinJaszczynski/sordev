<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\EventTemplate
 */
class PublicPackageDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...(new PublicPackageResource($this->resource))->toArray($request),
            'event_description' => $this->event_description,
            'full_image_url' => $this->full_image_url,
            'transport_notes' => $this->transport_notes,
            'start_places' => $this->whenLoaded('startingPlaceAvailabilities', function () {
                return $this->startingPlaceAvailabilities
                    ->filter(fn ($row) => (bool) ($row->available ?? false) && $row->startPlace)
                    ->map(fn ($row) => [
                        'id' => $row->startPlace->id,
                        'name' => $row->startPlace->name,
                    ])
                    ->unique('id')
                    ->values();
            }),
            'program_points' => $this->whenLoaded('programPoints', function () {
                return $this->programPoints->map(fn ($point) => [
                    'id' => $point->id,
                    'name' => $point->name,
                    'day' => (int) ($point->day ?? 1),
                    'order' => (int) ($point->order ?? 0),
                    'description' => $point->show_description ? $point->description : null,
                    'is_hotel' => (bool) ($point->is_hotel ?? false),
                    'is_transport' => (bool) ($point->is_transport ?? false),
                ])->values();
            }),
        ];
    }
}
