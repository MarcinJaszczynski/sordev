<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Support\ClientPortalMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Event
 */
class TripSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'status' => $this->status,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'duration_days' => $this->duration_days,
            'date_range_label' => ClientPortalMedia::dateRangeLabel($this->resource),
            'cover_url' => ClientPortalMedia::coverUrl($this->resource),
            'start_place' => $this->whenLoaded('startPlace', fn () => $this->startPlace ? [
                'id' => $this->startPlace->id,
                'name' => $this->startPlace->name,
            ] : null),
        ];
    }
}
