<?php

namespace App\Services;

use App\Models\FaqEntry;
use Illuminate\Support\Collection;

class FaqQueryService
{
    public function forHome(int $limit = 8): Collection
    {
        return $this->queryForScopes([FaqEntry::SCOPE_HOME, FaqEntry::SCOPE_GLOBAL], null, $limit);
    }

    public function forAbout(int $limit = 8): Collection
    {
        return $this->queryForScopes([FaqEntry::SCOPE_ABOUT, FaqEntry::SCOPE_GLOBAL], null, $limit);
    }

    public function forFaqPage(): Collection
    {
        return FaqEntry::query()
            ->published()
            ->where(function ($q) {
                $q->where('scope', FaqEntry::SCOPE_FAQ_PAGE)
                    ->orWhere('scope', FaqEntry::SCOPE_GLOBAL);
            })
            ->whereNull('event_template_id')
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function forPackage(?int $eventTemplateId, int $limit = 5): Collection
    {
        return FaqEntry::query()
            ->published()
            ->where(function ($q) use ($eventTemplateId) {
                $q->where('scope', FaqEntry::SCOPE_PACKAGE)
                    ->orWhere('scope', FaqEntry::SCOPE_GLOBAL);

                if ($eventTemplateId) {
                    $q->orWhere('event_template_id', $eventTemplateId);
                }
            })
            ->orderByRaw('CASE WHEN event_template_id IS NOT NULL THEN 0 ELSE 1 END')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    public function groupedForFaqPage(): Collection
    {
        return $this->forFaqPage()->groupBy('category');
    }

    private function queryForScopes(array $scopes, ?int $eventTemplateId, int $limit): Collection
    {
        return FaqEntry::query()
            ->published()
            ->whereIn('scope', $scopes)
            ->when($eventTemplateId, fn ($q) => $q->where(function ($inner) use ($eventTemplateId) {
                $inner->whereNull('event_template_id')
                    ->orWhere('event_template_id', $eventTemplateId);
            }), fn ($q) => $q->whereNull('event_template_id'))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }
}
