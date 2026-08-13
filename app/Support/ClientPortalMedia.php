<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Event;

/**
 * Okładki w portalu — full image + CSS object-center (bez thumb crop 300×300).
 */
final class ClientPortalMedia
{
    public static function coverUrl(?Event $event): ?string
    {
        if (! $event) {
            return null;
        }

        $template = $event->relationLoaded('eventTemplate')
            ? $event->eventTemplate
            : $event->eventTemplate()->first();

        if (! $template) {
            return null;
        }

        $full = $template->full_image_url ?: null;
        if (filled($full)) {
            return $full;
        }

        $preview = $template->preview_image_url ?: null;

        return filled($preview) ? $preview : null;
    }

    public static function dateRangeLabel(?Event $event): ?string
    {
        if (! $event?->start_date) {
            return null;
        }

        $label = $event->start_date->format('d.m.Y');
        if ($event->end_date) {
            $label .= ' – '.$event->end_date->format('d.m.Y');
        }

        return $label;
    }
}
