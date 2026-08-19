<?php

namespace App\Support;

use App\Models\EventProgramPoint;
use App\Models\EventTemplateProgramPoint;
use Illuminate\Support\Str;

/**
 * Etykiety wyników wyszukiwania katalogu punktów programu.
 *
 * Cel: odróżnić set od punktu, miasto/tagi i wariant ceny — bez otwierania rekordu.
 */
final class ProgramPointSearchDisplay
{
    public static function kindLabel(EventTemplateProgramPoint $point): string
    {
        $childCount = (int) ($point->children_count ?? $point->children()->count());
        if ($childCount > 0) {
            return 'Set ('.$childCount.')';
        }

        $parentCount = (int) ($point->parents_count ?? $point->parents()->count());

        return $parentCount > 0 ? 'Podpunkt' : 'Punkt';
    }

    public static function metaLine(EventTemplateProgramPoint $point): string
    {
        $parts = [];

        $tags = $point->relationLoaded('tags')
            ? $point->tags
            : $point->tags()->get();

        $tagNames = $tags->pluck('name')->filter()->take(4)->implode(', ');
        if ($tagNames !== '') {
            $parts[] = $tagNames;
        }

        $duration = self::durationLabel(
            (int) ($point->duration_hours ?? 0),
            (int) ($point->duration_minutes ?? 0),
        );
        if ($duration !== null) {
            $parts[] = $duration;
        }

        $price = (float) ($point->unit_price ?? 0);
        if ($price > 0.009) {
            $symbol = CurrencyAmountDisplay::symbol($point->currency);
            $parts[] = number_format($price, 2, ',', ' ').' '.$symbol;
        }

        $parentName = $point->relationLoaded('parents')
            ? $point->parents->first()?->name
            : $point->parents()->orderBy('event_template_program_points.name')->value('event_template_program_points.name');
        if (filled($parentName)) {
            $parts[] = 'w: '.$parentName;
        }

        return implode(' · ', $parts);
    }

    public static function snippet(EventTemplateProgramPoint $point, int $limit = 90): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($point->description ?? ''))) ?? '');
        if ($text === '') {
            $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($point->office_notes ?? ''))) ?? '');
        }

        if ($text === '') {
            return null;
        }

        return Str::limit($text, $limit);
    }

    public static function html(EventTemplateProgramPoint $point): string
    {
        $kindLabel = self::kindLabel($point);
        $kind = e($kindLabel);
        $name = e((string) $point->name);
        $meta = self::metaLine($point);
        $snippet = self::snippet($point);
        $kindClass = str_starts_with($kindLabel, 'Set')
            ? 'bg-violet-100 text-violet-800'
            : ($kindLabel === 'Podpunkt' ? 'bg-slate-100 text-slate-700' : 'bg-sky-100 text-sky-800');

        $html = '<div class="leading-snug py-0.5 text-left">';
        $html .= '<div class="flex flex-wrap items-baseline gap-x-1.5 gap-y-0.5">';
        $html .= '<span class="inline-flex rounded px-1 py-px text-[10px] font-semibold uppercase tracking-wide '.$kindClass.'">'.$kind.'</span>';
        $html .= '<span class="font-medium text-gray-900">'.$name.'</span>';
        $html .= '</div>';

        if ($meta !== '') {
            $html .= '<div class="mt-0.5 text-[11px] text-gray-600">'.e($meta).'</div>';
        }
        if ($snippet !== null) {
            $html .= '<div class="mt-0.5 text-[11px] text-gray-400">'.e($snippet).'</div>';
        }
        $html .= '</div>';

        return $html;
    }

    public static function selectedLabel(EventTemplateProgramPoint $point): string
    {
        $parts = [self::kindLabel($point), (string) $point->name];
        $meta = self::metaLine($point);
        if ($meta !== '') {
            $parts[] = $meta;
        }

        return implode(' · ', $parts);
    }

    public static function eventPointKindLabel(EventProgramPoint $point): string
    {
        $childCount = (int) ($point->children_count ?? $point->children()->count());
        if ($childCount > 0) {
            return 'Set imprezy ('.$childCount.')';
        }

        return filled($point->parent_id) ? 'Podpunkt imprezy' : 'Punkt imprezy';
    }

    public static function eventPointHtml(EventProgramPoint $point): string
    {
        $kind = e(self::eventPointKindLabel($point));
        $name = e((string) ($point->name ?: $point->templatePoint?->name ?: 'Punkt #'.$point->id));
        $meta = e(self::eventPointMetaLine($point));

        $html = '<div class="leading-snug py-0.5 text-left">';
        $html .= '<div class="flex flex-wrap items-baseline gap-x-1.5 gap-y-0.5">';
        $html .= '<span class="inline-flex rounded px-1 py-px text-[10px] font-semibold uppercase tracking-wide bg-amber-100 text-amber-800">'.$kind.'</span>';
        $html .= '<span class="font-medium text-gray-900">'.$name.'</span>';
        $html .= '</div>';
        if ($meta !== '') {
            $html .= '<div class="mt-0.5 text-[11px] text-gray-600">'.$meta.'</div>';
        }
        $html .= '</div>';

        return $html;
    }

    public static function eventPointSelectedLabel(EventProgramPoint $point): string
    {
        $parts = [
            self::eventPointKindLabel($point),
            (string) ($point->name ?: $point->templatePoint?->name ?: 'Punkt #'.$point->id),
        ];
        $meta = self::eventPointMetaLine($point);
        if ($meta !== '') {
            $parts[] = $meta;
        }

        return implode(' · ', $parts);
    }

    private static function eventPointMetaLine(EventProgramPoint $point): string
    {
        $parts = [];

        $event = $point->event;
        if ($event) {
            $eventLabel = trim(($event->code ? '['.$event->code.'] ' : '').($event->name ?? ''));
            if ($eventLabel !== '') {
                $parts[] = $eventLabel;
            }
        }

        if (filled($point->contractor?->name)) {
            $parts[] = $point->contractor->name;
        }

        $city = $point->contractorLocation?->city;
        if (filled($city)) {
            $parts[] = $city;
        } elseif (filled($point->contractorLocation?->name)) {
            $parts[] = $point->contractorLocation->name;
        }

        return implode(' · ', $parts);
    }

    private static function durationLabel(int $hours, int $minutes): ?string
    {
        if ($hours <= 0 && $minutes <= 0) {
            return null;
        }

        if ($hours > 0 && $minutes > 0) {
            return $hours.'h '.$minutes.'m';
        }

        return $hours > 0 ? $hours.'h' : $minutes.' min';
    }
}
