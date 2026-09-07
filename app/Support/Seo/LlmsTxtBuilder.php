<?php

namespace App\Support\Seo;

use App\Models\BlogPost;
use App\Models\SeoSetting;
use App\Support\Region;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Generuje /llms.txt: opis serwisu + katalog realnych połączeń
 * (szablon × miasto wyjazdu ze strict local availability + ceną lokalną).
 *
 * Linkuje wyłącznie do istniejących pretty URL ofert — bez fikcyjnych landingów.
 */
class LlmsTxtBuilder
{
    public const CACHE_KEY = 'seo.llms_txt.v2';

    public const CACHE_TTL_SECONDS = 3600;

    /** Docelowy host produkcyjny w katalogu AI (nadpisywalny przez APP_LLMS_URL). */
    public const DEFAULT_PUBLIC_BASE = 'https://bprafa.pl';

    public function toString(bool $fresh = false): string
    {
        if ($fresh) {
            self::flushCache();
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, fn (): string => $this->build());
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    protected function build(): string
    {
        $base = $this->baseUrl();
        $org = SeoSetting::organization();
        $orgName = (string) ($org['name'] ?? 'Biuro Podróży RAFA');
        $description = (string) ($org['default_description'] ?? 'Wycieczki szkolne, zielone szkoły i wyjazdy firmowe.');

        $connections = $this->loadConnections();
        $startPlaces = $this->uniqueStartPlaces($connections);

        $lines = [];
        $lines[] = '# '.$orgName;
        $lines[] = '> '.$description;
        $lines[] = '>';
        $lines[] = '> Ten plik pomaga modelom AI i asystentom znaleźć aktualną ofertę wycieczek szkolnych.';
        $lines[] = '> Każdy link prowadzi do realnej oferty dostępnej z danego miasta wyjazdu.';
        $lines[] = '';

        $lines[] = '## O firmie';
        $lines[] = '- [O nas]('.$base.'/o-nas): kim jesteśmy i jak organizujemy wyjazdy';
        $lines[] = '- [Blog]('.$base.'/blog): artykuły o wycieczkach szkolnych';
        $lines[] = '- [Poradnik]('.$base.'/poradnik): praktyczne informacje dla organizatorów';
        $lines[] = '- [Dokumenty]('.$base.'/documents): wzory i materiały informacyjne';
        $lines[] = '- [Sitemap]('.$base.'/sitemap.xml): pełna mapa stron';
        $lines[] = '';

        $lines[] = '## Miasta wyjazdu';
        if ($startPlaces->isEmpty()) {
            $lines[] = '- Brak aktywnych miast wyjazdu w katalogu.';
        } else {
            $lines[] = 'Oferta jest regionalna: wybierz miasto startu, potem konkretną wycieczkę.';
            $lines[] = '';
            foreach ($startPlaces as $place) {
                $slug = $place['slug'];
                $name = $place['name'];
                $fromPhrase = PolishPlaceGenitive::withPreposition('z', $name);
                $lines[] = sprintf(
                    '- [Wycieczki szkolne %s](%s/%s/oferty): lista ofert · [start](%s/%s/) · [kontakt](%s/%s/contact) · [FAQ](%s/%s/faq)',
                    $fromPhrase,
                    $base,
                    $slug,
                    $base,
                    $slug,
                    $base,
                    $slug,
                    $base,
                    $slug,
                );
            }
        }
        $lines[] = '';

        $lines[] = '## Połączenia: wycieczka × miasto wyjazdu';
        $lines[] = 'Format: {długość} wycieczka do {kierunek} z {wyjazd} — „nazwa oferty”: URL';
        $lines[] = 'Uwzględnione są wyłącznie oferty aktywne, dostępne lokalnie (availability) i z ceną > 0 dla danego startu.';
        $lines[] = '';

        if ($connections->isEmpty()) {
            $lines[] = '- Brak aktywnych połączeń w katalogu.';
        } else {
            $currentFromId = null;
            $currentDays = null;
            foreach ($connections as $row) {
                $fromId = (int) $row->start_place_id;
                $days = (int) ($row->duration_days ?? 0);

                if ($fromId !== $currentFromId) {
                    if ($currentFromId !== null) {
                        $lines[] = '';
                    }
                    $fromName = (string) $row->from_name;
                    $lines[] = '### Wyjazdy '.PolishPlaceGenitive::withPreposition('z', $fromName);
                    $currentFromId = $fromId;
                    $currentDays = null;
                }

                if ($days !== $currentDays) {
                    $lines[] = '';
                    $lines[] = '#### '.$this->durationSectionHeading($days);
                    $currentDays = $days;
                }

                $lines[] = $this->connectionLine($row, $base);
            }
        }

        $lines[] = '';
        $lines[] = '## Blog (najnowsze)';
        foreach ($this->recentBlogLines($base) as $blogLine) {
            $lines[] = $blogLine;
        }

        $lines[] = '';
        $lines[] = '## Kontakt';
        if (! empty($org['phone'])) {
            $lines[] = '- Telefon: '.$org['phone'];
        }
        if (! empty($org['email'])) {
            $lines[] = '- E-mail: '.$org['email'];
        }
        $lines[] = '- Strona: '.$base.'/';
        $lines[] = '';
        $lines[] = '# Wygenerowano: '.now()->toIso8601String();
        $lines[] = '# Połączeń w katalogu: '.$connections->count();

        return implode("\n", $lines)."\n";
    }

    /**
     * @return Collection<int, object{
     *     id: int|string,
     *     name: string,
     *     slug: string|null,
     *     duration_days: int|string|null,
     *     start_place_id: int|string,
     *     from_name: string,
     *     to_name: string|null
     * }>
     */
    protected function loadConnections(): Collection
    {
        if (
            ! Schema::hasTable('event_templates')
            || ! Schema::hasTable('event_template_starting_place_availability')
            || ! Schema::hasTable('event_template_price_per_person')
            || ! Schema::hasTable('places')
        ) {
            return collect();
        }

        return DB::table('event_templates as t')
            ->join('event_template_starting_place_availability as a', function ($join): void {
                $join->on('a.event_template_id', '=', 't.id')
                    ->on('a.end_place_id', '=', 't.start_place_id')
                    ->where('a.available', '=', true);
            })
            ->join('places as from_p', 'from_p.id', '=', 'a.start_place_id')
            ->leftJoin('places as to_p', 'to_p.id', '=', 't.end_place_id')
            ->where('t.is_active', true)
            ->whereNull('t.deleted_at')
            ->where('from_p.starting_place', true)
            ->whereExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('event_template_price_per_person as pp')
                    ->whereColumn('pp.event_template_id', 't.id')
                    ->whereColumn('pp.start_place_id', 'a.start_place_id')
                    ->where('pp.price_per_person', '>', 0);
            })
            ->whereRaw(
                'a.id = (
                    SELECT MAX(a2.id)
                    FROM event_template_starting_place_availability a2
                    WHERE a2.event_template_id = t.id
                      AND a2.start_place_id = a.start_place_id
                      AND a2.end_place_id = t.start_place_id
                )'
            )
            ->orderBy('from_p.name')
            ->orderBy('t.duration_days')
            ->orderBy('to_p.name')
            ->orderBy('t.name')
            ->select([
                't.id',
                't.name',
                't.slug',
                't.duration_days',
                'a.start_place_id',
                'from_p.name as from_name',
                'to_p.name as to_name',
            ])
            ->get();
    }

    /**
     * @param  Collection<int, object>  $connections
     * @return Collection<int, array{id: int, name: string, slug: string}>
     */
    protected function uniqueStartPlaces(Collection $connections): Collection
    {
        return $connections
            ->map(fn (object $row): array => [
                'id' => (int) $row->start_place_id,
                'name' => (string) $row->from_name,
                'slug' => Str::slug((string) $row->from_name) ?: Region::slugForLinks((int) $row->start_place_id),
            ])
            ->unique('id')
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    protected function durationSectionHeading(int $days): string
    {
        if ($days <= 0) {
            return 'Bez określonej długości';
        }

        if ($days === 1) {
            return '1-dniowe';
        }

        return $days.'-dniowe';
    }

    protected function connectionLine(object $row, string $base): string
    {
        $days = (int) ($row->duration_days ?? 0);
        $duration = $days === 1 ? 'Jednodniowa' : $days.'-dniowa';
        $fromPhrase = PolishPlaceGenitive::withPreposition('z', (string) $row->from_name);
        $title = $this->cleanTitle((string) $row->name);
        $url = $this->prettyOfferUrl($base, (int) $row->start_place_id, (string) $row->from_name, $days, (int) $row->id, (string) ($row->slug ?: ''));

        $toName = trim((string) ($row->to_name ?? ''));
        if ($toName !== '') {
            $toPhrase = PolishPlaceGenitive::withPreposition('do', $toName);

            return sprintf('- %s wycieczka %s %s — „%s”: %s', $duration, $toPhrase, $fromPhrase, $title, $url);
        }

        return sprintf('- %s wycieczka szkolna %s — „%s”: %s', $duration, $fromPhrase, $title, $url);
    }

    protected function prettyOfferUrl(
        string $base,
        int $startPlaceId,
        string $fromName,
        int $durationDays,
        int $templateId,
        string $slug,
    ): string {
        $regionSlug = Str::slug($fromName) ?: Region::slugForLinks($startPlaceId);
        $offerSlug = $slug !== '' ? $slug : 'oferta';
        $dayLength = $durationDays.'-dniowe';

        return $base.'/'.$regionSlug.'/'.$dayLength.'/'.$templateId.'/'.$offerSlug;
    }

    protected function cleanTitle(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);

        return str_replace(['„', '”', '"'], "'", $name);
    }

    /**
     * @return list<string>
     */
    protected function recentBlogLines(string $base): array
    {
        if (! Schema::hasTable('blog_posts')) {
            return ['- Brak wpisów.'];
        }

        try {
            $posts = BlogPost::query()
                ->published()
                ->latest('published_at')
                ->limit(12)
                ->get(['title', 'slug']);
        } catch (\Throwable) {
            return ['- Brak wpisów.'];
        }

        if ($posts->isEmpty()) {
            return ['- Brak opublikowanych wpisów.'];
        }

        return $posts->map(function (BlogPost $post) use ($base): string {
            $title = $this->cleanTitle((string) $post->title);
            $slug = (string) $post->slug;

            return sprintf('- [%s](%s/blog/%s)', $title, $base, $slug);
        })->all();
    }

    protected function baseUrl(): string
    {
        $configured = (string) (config('app.llms_url') ?: self::DEFAULT_PUBLIC_BASE);

        return rtrim($configured !== '' ? $configured : self::DEFAULT_PUBLIC_BASE, '/');
    }
}
