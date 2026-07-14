<?php

namespace App\Support\Reservations;

use App\Models\Reservation;
use App\Models\ReservationAttachment;
use Illuminate\Support\Facades\Storage;

class ReservationAttachmentStore
{
    /**
     * @param  array<int, string|null>  $filePaths
     */
    public static function storeMany(Reservation $reservation, array $filePaths, ?int $userId): void
    {
        foreach (array_filter($filePaths) as $path) {
            static::storeOne($reservation, (string) $path, $userId);
        }
    }

    public static function storeOne(Reservation $reservation, string $path, ?int $userId): ReservationAttachment
    {
        return $reservation->attachments()->create([
            'file_path' => $path,
            'name' => basename($path),
            'mime_type' => Storage::exists($path) ? Storage::mimeType($path) : null,
            'size' => Storage::exists($path) ? Storage::size($path) : null,
            'user_id' => $userId,
        ]);
    }
}
