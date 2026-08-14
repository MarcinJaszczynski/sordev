<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\EventTemplate
 */
class PublicPackageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'subtitle' => $this->subtitle,
            'duration_days' => (int) $this->duration_days,
            'preview_image_url' => $this->preview_image_url,
            'event_types' => $this->whenLoaded('eventTypes', fn () => $this->eventTypes->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
            ])->values()),
            'transport_types' => $this->whenLoaded('transportTypes', fn () => $this->transportTypes->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
            ])->values()),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
            ])->values()),
        ];
    }
}
