<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Punkt programu — bez cen i notatek biurowych.
 * audience: pilot|client|public
 *
 * @mixin \App\Models\EventProgramPoint
 */
class ProgramPointResource extends JsonResource
{
    public function __construct($resource, private readonly string $audience = 'client')
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $includeDescription = (bool) ($this->show_description ?? true);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'day' => (int) ($this->day ?? 1),
            'order' => (int) ($this->order ?? 0),
            'parent_id' => $this->parent_id,
            'start_time' => $this->hide_times ? null : $this->start_time,
            'end_time' => $this->hide_times ? null : $this->end_time,
            'hide_times' => (bool) ($this->hide_times ?? false),
            'description' => $includeDescription ? $this->description : null,
            'pilot_notes' => $this->when($this->audience === 'pilot', $this->pilot_notes),
            'is_hotel' => (bool) ($this->is_hotel ?? false),
            'is_transport' => (bool) ($this->is_transport ?? false),
            'is_hotel_service' => (bool) ($this->is_hotel_service ?? false),
        ];
    }
}
