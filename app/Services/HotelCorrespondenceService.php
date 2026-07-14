<?php

namespace App\Services;

use App\Models\Event;
use App\Models\HotelCorrespondenceLog;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class HotelCorrespondenceService
{
    /**
     * @return Collection<int, HotelCorrespondenceLog>
     */
    public function logsForEvent(Event $event): Collection
    {
        return HotelCorrespondenceLog::query()
            ->with(['contractor', 'hotelStay', 'author'])
            ->where('event_id', $event->id)
            ->orderByDesc('contacted_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Event $event, User $user, array $data): HotelCorrespondenceLog
    {
        $attachmentPath = null;

        if (! empty($data['attachment'])) {
            $attachmentPath = $data['attachment']->store('hotel-correspondence/'.$event->id, 'public');
        }

        return HotelCorrespondenceLog::query()->create([
            'event_id' => $event->id,
            'contractor_id' => $data['contractor_id'] ?? null,
            'event_hotel_stay_id' => $data['event_hotel_stay_id'] ?? null,
            'direction' => in_array($data['direction'] ?? '', array_keys(HotelCorrespondenceLog::$directions), true)
                ? $data['direction']
                : HotelCorrespondenceLog::DIRECTION_OUTBOUND,
            'subject' => filled($data['subject'] ?? null) ? trim((string) $data['subject']) : null,
            'body' => filled($data['body'] ?? null) ? trim((string) $data['body']) : null,
            'contact_person' => filled($data['contact_person'] ?? null) ? trim((string) $data['contact_person']) : null,
            'contacted_at' => $data['contacted_at'] ?? now(),
            'attachment_path' => $attachmentPath,
            'created_by' => $user->id,
        ]);
    }

    public function attachmentUrl(HotelCorrespondenceLog $log): ?string
    {
        if (blank($log->attachment_path)) {
            return null;
        }

        return Storage::disk('public')->url($log->attachment_path);
    }
}
