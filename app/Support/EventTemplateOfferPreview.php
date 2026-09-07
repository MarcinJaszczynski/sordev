<?php

namespace App\Support;

use App\Models\EventTemplate;
use App\Models\Place;
use Illuminate\Support\Str;

/**
 * Podgląd publicznej oferty szablonu z wybranym miejscem wyjazdu.
 */
final class EventTemplateOfferPreview
{
    /**
     * @return array<int, string>
     */
    public static function startPlaceOptions(EventTemplate $template): array
    {
        return Place::startingPlaceSelectOptionsForTemplate($template->id);
    }

    public static function defaultStartPlaceId(EventTemplate $template): ?int
    {
        $options = self::startPlaceOptions($template);

        if ($options === []) {
            return null;
        }

        foreach ($options as $id => $name) {
            if (Str::slug((string) $name) === 'warszawa' || mb_strtolower((string) $name) === 'warszawa') {
                return (int) $id;
            }
        }

        return (int) array_key_first($options);
    }

    public static function url(EventTemplate $template, int $startPlaceId): string
    {
        return $template->prettyUrl($startPlaceId);
    }
}
