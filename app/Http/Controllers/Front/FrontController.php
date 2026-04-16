<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use App\Models\EventTemplate;
use App\Models\EventTemplatePricePerPerson;
use App\Models\EventTemplateStartingPlaceAvailability;
use App\Models\Currency;
use App\Models\Place;
use App\Models\EventType;
use App\Models\Tag;
use App\Models\TransportType;
use App\Models\BlogPost;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;

/**
 * FrontController
 *
 * Kontroler odpowiadający za publiczny frontend serwisu: strona główna,
 * katalog ofert (/oferty), wyświetlanie szczegółów ofert oraz partiale
 * używane do ajax-owego ładowania wyników.
 *
 * Zawiera logikę rozwiązywania `start_place_id` (slug -> param -> cookie -> domyślna Warszawa),
 * filtrowania ofert według długości, typu, tagów oraz bezpieczeństwo przy obliczaniu
 * i prezentacji minimalnej ceny PLN.
 */
class FrontController extends Controller
{
    /**
     * Remove diacritics from a string (for fuzzy search)
     */
    private function removeDiacritics($string)
    {
        $diacritics = [
            'ą' => 'a',
            'ć' => 'c',
            'ę' => 'e',
            'ł' => 'l',
            'ń' => 'n',
            'ó' => 'o',
            'ś' => 's',
            'ż' => 'z',
            'ź' => 'z',
            'Ą' => 'A',
            'Ć' => 'C',
            'Ę' => 'E',
            'Ł' => 'L',
            'Ń' => 'N',
            'Ó' => 'O',
            'Ś' => 'S',
            'Ż' => 'Z',
            'Ź' => 'Z',
        ];
        return strtr($string, $diacritics);
    }

    private function compareNamesLocaleAware(?string $a, ?string $b): int
    {
        $a = (string) $a;
        $b = (string) $b;

        $aNorm = $this->normalizeNameForSort($a);
        $bNorm = $this->normalizeNameForSort($b);
        if ($aNorm === $bNorm) {
            return 0;
        }

        static $collator = null;
        if ($collator === null) {
            try {
                if (class_exists(\Collator::class)) {
                    $collator = new \Collator('pl_PL');
                    $collator->setStrength(\Collator::SECONDARY);
                } else {
                    $collator = false;
                }
            } catch (\Throwable $e) {
                $collator = false;
            }
        }

        if ($collator instanceof \Collator) {
            $res = $collator->compare($aNorm, $bNorm);
            if (is_int($res)) {
                if (config('app.debug')) {
                    static $cmpCount = 0;
                    $cmpCount++;
                    if ($cmpCount <= 20) {
                        Log::debug('[compareNamesLocaleAware] Collator', ['a' => $aNorm, 'b' => $bNorm, 'res' => $res]);
                    }
                }
                return $res;
            }
        }

        $aKey = str_replace('ł', 'l~', $aNorm);
        $bKey = str_replace('ł', 'l~', $bNorm);
        $aKey = $this->removeDiacritics($aKey);
        $bKey = $this->removeDiacritics($bKey);

        return $aKey <=> $bKey;
    }

    private function normalizeNameForSort(string $name): string
    {
        $name = trim($name);
        // Usuń wiodące znaki inne niż litera/cyfra (np. cudzysłów, myślnik)
        $name = preg_replace('/^[^\p{L}\p{N}]+/u', '', $name) ?? $name;
        // Redukcja wielu spacji
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
        return mb_strtolower($name);
    }

    private function getCachedStartPlaces(): Collection
    {
        return Cache::remember('front.start_places.available', 600, function () {
            $startPlaceIds = EventTemplateStartingPlaceAvailability::query()
                ->where('available', true)
                ->select('start_place_id')
                ->distinct()
                ->pluck('start_place_id');

            $sortKey = $this->buildPolishSortKeySql('name');

            return Place::whereIn('id', $startPlaceIds)
                ->where('starting_place', true)
                ->orderByRaw("$sortKey ASC, LOWER(name) ASC")
                ->get();
        });
    }

    private function getCachedEventTypes(): Collection
    {
        return Cache::remember('front.event_types', 600, function () {
            $sortKey = $this->buildPolishSortKeySql('name');

            return EventType::orderByRaw("$sortKey ASC, LOWER(name) ASC")->get();
        });
    }

    private function getCachedTransportTypes(): Collection
    {
        return Cache::remember('front.transport_types', 600, function () {
            $sortKey = $this->buildPolishSortKeySql('name');

            return TransportType::orderByRaw("$sortKey ASC, LOWER(name) ASC")->get();
        });
    }

    private function getCachedTags(): Collection
    {
        return Cache::remember('front.tags', 600, function () {
            $sortKey = $this->buildPolishSortKeySql('name');

            return Tag::orderByRaw("$sortKey ASC, LOWER(name) ASC")->get();
        });
    }

    /**
     * Zwraca SQL-owy klucz sortowania dla nazw z aproksymacją polskiej kolacji:
     *  - ł po l (mapowane na l~)
     *  - ą/ć/ę/ń/ó/ś/ź/ż po literze podstawowej; ź przed ż (ź -> z~1, ż -> z~2)
     *  Działa na LOWER($column), aby nie duplikować mapowań dla wielkich liter.
     */
    private function buildPolishSortKeySql(string $column = 'name'): string
    {
        $col = "LOWER($column)";
        // Uwaga: kolejność REPLACE nie ma krytycznego znaczenia, ale zachowujemy konsekwencję.
        // Najpierw litery ze znacznikami pozycji:
        $expr = "REPLACE($col, 'ł', 'l~')";
        $expr = "REPLACE($expr, 'ą', 'a~')";
        $expr = "REPLACE($expr, 'ć', 'c~')";
        $expr = "REPLACE($expr, 'ę', 'e~')";
        $expr = "REPLACE($expr, 'ń', 'n~')";
        $expr = "REPLACE($expr, 'ó', 'o~')";
        $expr = "REPLACE($expr, 'ś', 's~')";
        // Rozróżnij ź i ż, ustawiając ź przed ż
        $expr = "REPLACE($expr, 'ź', 'z~1')";
        $expr = "REPLACE($expr, 'ż', 'z~2')";
        return $expr;
    }

    private function buildDiacriticsFreeSql(string $column = 'name'): string
    {
        $expr = "LOWER($column)";
        $map = [
            'ą' => 'a',
            'ć' => 'c',
            'ę' => 'e',
            'ł' => 'l',
            'ń' => 'n',
            'ó' => 'o',
            'ś' => 's',
            'ż' => 'z',
            'ź' => 'z',
        ];

        foreach ($map as $from => $to) {
            $expr = "REPLACE($expr, '$from', '$to')";
        }

        return $expr;
    }

    private function resolveStartPlaceIdFromSlug(?string $regionSlug): ?int
    {
        if (!$regionSlug || $regionSlug === 'region') {
            return null;
        }

        static $startingPlacesCache = null;
        if ($startingPlacesCache === null) {
            $startingPlacesCache = Place::where('starting_place', true)->get();
        }

        $place = $startingPlacesCache->first(function ($pl) use ($regionSlug) {
            return str()->slug($pl->name) === $regionSlug;
        });

        return $place?->id;
    }

    private function applyStrictLocalAvailabilityFilter($query, int $startPlaceId)
    {
        return $query
            ->whereExists(function ($sub) use ($startPlaceId) {
                $sub->select(DB::raw('1'))
                    ->from('event_template_starting_place_availability as a')
                    ->whereColumn('a.event_template_id', 'event_templates.id')
                    ->where('a.start_place_id', $startPlaceId)
                    ->whereColumn('a.end_place_id', 'event_templates.start_place_id')
                    ->where('a.available', true)
                    ->whereRaw(
                        'a.id = (
                            SELECT MAX(a2.id)
                            FROM event_template_starting_place_availability a2
                            WHERE a2.event_template_id = event_templates.id
                              AND a2.start_place_id = ?
                              AND a2.end_place_id = event_templates.start_place_id
                        )',
                        [$startPlaceId]
                    );
            })
            ->whereHas('pricesPerPerson', function ($q) use ($startPlaceId) {
                $q->where('start_place_id', $startPlaceId)
                    ->where('price_per_person', '>', 0);
            });
    }

    private function hasStrictLocalAvailabilityForTemplate(EventTemplate $eventTemplate, int $startPlaceId): bool
    {
        if (! $eventTemplate->start_place_id) {
            return false;
        }

        $latestAvailability = EventTemplateStartingPlaceAvailability::query()
            ->where('event_template_id', $eventTemplate->id)
            ->where('start_place_id', $startPlaceId)
            ->where('end_place_id', $eventTemplate->start_place_id)
            ->orderByDesc('id')
            ->first();

        if (! $latestAvailability || ! $latestAvailability->available) {
            return false;
        }

        return EventTemplatePricePerPerson::query()
            ->where('event_template_id', $eventTemplate->id)
            ->where('start_place_id', $startPlaceId)
            ->where('price_per_person', '>', 0)
            ->exists();
    }

    private function redirectToRegionalOffers(string $regionSlug)
    {
        return redirect()
            ->route('packages', ['regionSlug' => $regionSlug])
            ->with('warning', 'Oferta jest niedostępna dla wybranego miasta.');
    }

    public function blog(Request $request)
    {
        $orderExpression = DB::raw('COALESCE(published_at, created_at)');

        $search = trim((string) $request->query('q', ''));
        $sort = $request->query('sort', 'newest');

        // If there's a search query, skip featured spotlight and show filtered results only
        $featuredPosts = collect();

        // Base query for posts listing
        $postsQuery = BlogPost::published();

        if (!empty($search)) {
            $postsQuery->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('excerpt', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        } else {
            // Featured posts (max 3) only when not searching
            $featuredPosts = BlogPost::published()
                ->where('is_featured', true)
                ->orderByDesc($orderExpression)
                ->orderByDesc('id')
                ->take(3)
                ->get();
        }

        // Apply sorting
        switch ($sort) {
            case 'oldest':
                $postsQuery->orderBy($orderExpression, 'asc')->orderBy('id', 'asc');
                break;
            case 'title_asc':
                $postsQuery->orderBy('title', 'asc');
                break;
            case 'title_desc':
                $postsQuery->orderBy('title', 'desc');
                break;
            case 'newest':
            default:
                $postsQuery->orderByDesc($orderExpression)->orderByDesc('id');
                break;
        }

        // Paginate results for the grid (12 per page) - show all published posts
        $posts = $postsQuery->paginate(12)->withQueryString();

        return view('front.blog', compact('posts', 'featuredPosts', 'search', 'sort'));
    }

    public function blogPost($slug)
    {
        $orderExpression = DB::raw('COALESCE(published_at, created_at)');

        $blogPost = BlogPost::published()
            ->where('slug', $slug)
            ->firstOrFail();

        $pivotDate = $blogPost->published_at ?? $blogPost->created_at ?? now();

        // Previous/next navigation
        $previousPost = BlogPost::published()
            ->whereRaw('COALESCE(published_at, created_at) < ?', [$pivotDate])
            ->orderByDesc($orderExpression)
            ->orderByDesc('id')
            ->first();
        $nextPost = BlogPost::published()
            ->whereRaw('COALESCE(published_at, created_at) > ?', [$pivotDate])
            ->orderBy($orderExpression)
            ->orderBy('id')
            ->first();

        return view('front.blog-post', compact('blogPost', 'previousPost', 'nextPost'));
    }
    public function directorypackages(Request $request)
    {
        $directoryRequiresToken = $request->boolean('requires_turnstile');
        if (!$directoryRequiresToken && $request->filled('cf-turnstile-response')) {
            $directoryRequiresToken = true;
        }

        $directoryTurnstileOk = $this->verifyTurnstileToken($request, 'directory_filter', $directoryRequiresToken);
        if ($directoryRequiresToken && !$directoryTurnstileOk) {
            $regionSlug = $request->route('regionSlug') ?? 'region';
            return redirect()->route('directory-packages', ['regionSlug' => $regionSlug])->with('error', 'Nie udało się zweryfikować zabezpieczenia. Spróbuj ponownie.');
        }

        $form_name = $request->destination_name ?? $request->name;
        $form_min_price = $request->min_price;
        $form_max_price = $request->max_price;
        $form_destination_id = $request->destination_id;
        $form_length_id = $request->length_id;
        $sort_by = $request->sort_by ?: 'name_asc';
        $event_type_id = $request->event_type_id;

        $region_id = $request->region_id ?? Cookie::get('region_id', 16);

        // NEW: start places (for unified selector functionality, appearance unchanged)
        $startPlaces = $this->getCachedStartPlaces();

        // Handle regionSlug from URL (like /warszawa/directory-packages)
        $currentStartPlaceId = null;
        $regionSlug = $request->route('regionSlug');
        if ($regionSlug && $regionSlug !== 'region') {
            $place = $startPlaces->first(function ($pl) use ($regionSlug) {
                return str()->slug($pl->name) === $regionSlug;
            });
            if ($place) {
                $currentStartPlaceId = $place->id;
                Cookie::queue('start_place_id', (string)$currentStartPlaceId, 60 * 24 * 365);
            }
        }
        if (!$currentStartPlaceId) {
            $currentStartPlaceId = $request->get('start_place_id');
        }
        if (!$currentStartPlaceId) {
            $currentStartPlaceId = $request->cookie('start_place_id');
        }
        if ($currentStartPlaceId && !$startPlaces->where('id', (int)$currentStartPlaceId)->first()) {
            $currentStartPlaceId = null; // invalid -> reset
        }

        // Pobierz wszystkie Event Types dla filtra
        $sortKey = $this->buildPolishSortKeySql('name');
        $eventTypes = \App\Models\EventType::orderByRaw("$sortKey ASC, LOWER(name) ASC")->get();

        if ($request->region_id) {
            Cookie::queue('region_id', $request->region_id, 60 * 24 * 365); // 1 year expiration
        }

        $mapEventTemplate = function ($eventTemplate) use ($currentStartPlaceId) {
            $eventTemplate->featured_photo = $eventTemplate->featured_image ?: 'default.png';
            $eventTemplate->description = $eventTemplate->event_description;
            $eventTemplate->length_id = $eventTemplate->duration_days;
            // Ujednolicona logika minimalnej ceny PLN (lokalne ceny > globalne)
            $eventTemplate->price = null;
            $eventTemplate->computed_price = null;
            if ($eventTemplate->relationLoaded('pricesPerPerson')) {
                $all = $eventTemplate->pricesPerPerson;
                // For listing we prefer a price applicable for typical group size ~40-55
                $min = $this->resolveMinPlnFromPrices($all, $currentStartPlaceId, 40, 55);
                if ($min === null) {
                    // Fallback: dowolny wariant qty (nadal najnowsze per qty, PLN, lokalne)
                    $min = $this->resolveMinPlnFromPrices($all, $currentStartPlaceId, null, null);
                }
                if ($min !== null) {
                    $eventTemplate->computed_price = (float)$min;
                    $eventTemplate->price = (float)$min;
                }
                // Set relation to candidate prices (local if start place provided, otherwise all >0)
                if ($currentStartPlaceId) {
                    $candidate = $all->filter(fn($p) => $p->price_per_person > 0 && (int)$p->start_place_id === (int)$currentStartPlaceId)->values();
                } else {
                    $candidate = $all->filter(fn($p) => $p->price_per_person > 0)->values();
                }
                $eventTemplate->setRelation('pricesPerPerson', $candidate);
                // Diagnostic logging for specific template id (57)
                if (config('app.debug') && isset($eventTemplate->id) && (int)$eventTemplate->id === 57) {
                    try {
                        Log::debug('mapEventTemplate(): price_diag', [
                            'event_template_id' => $eventTemplate->id,
                            'current_start_place_id' => $currentStartPlaceId,
                            'candidate_prices' => $candidate->map(fn($p) => [$p->id, $p->start_place_id, $p->event_template_qty_id, $p->price_per_person, $p->currency?->code ?? null])->values(),
                            'computed_price' => $eventTemplate->computed_price,
                        ]);
                    } catch (\Throwable $e) {
                    }
                }
            }
            $eventTemplate->old_price = null;
            $eventTemplate->transport_id = null;
            $eventTemplate->destination_id = null;
            $eventTemplate->region_id = null;
            $eventTemplate->length = (object) [
                'id' => $eventTemplate->duration_days,
                'name' => $eventTemplate->duration_days == 1 ? '1 dzień' : $eventTemplate->duration_days . ' dni'
            ];
            $eventTemplate->transport = (object) [
                'id' => null,
                'name' => 'Nie określono'
            ];
            return $eventTemplate;
        };

        // Helper closure to fetch templates filtered by selected start_place availability (if chosen)
        $fetchByDuration = function ($operator, $value, $limit) use ($mapEventTemplate, $request, $currentStartPlaceId) {
            $q = \App\Models\EventTemplate::query()
                ->where('is_active', true)
                ->when($operator === '>=', fn($qq) => $qq->where('duration_days', '>=', $value), fn($qq) => $qq->where('duration_days', $value))
                ->orderByDesc('id') // deterministic-ish newest first
                ->with([
                    'startingPlaceAvailabilities',
                    'pricesPerPerson.eventTemplateQty',
                    'pricesPerPerson.currency',
                    'pricesPerPerson.startPlace'
                ]);
            $startPlaceId = $request->get('start_place_id') ?: $request->cookie('start_place_id');
            if ($startPlaceId) {
                // Wymagaj NAJNOWSZEJ dostępności = true (MAX(id) dla danego start_place_id)
                $q->whereRaw(
                    "EXISTS (\n                        SELECT 1 FROM event_template_starting_place_availability a\n                        WHERE a.event_template_id = event_templates.id\n                          AND a.start_place_id = ?\n                          AND a.available = 1\n                          AND a.id = (\n                            SELECT MAX(id) FROM event_template_starting_place_availability\n                            WHERE event_template_id = event_templates.id AND start_place_id = ?\n                          )\n                    )",
                    [(int)$startPlaceId, (int)$startPlaceId]
                )->whereHas('pricesPerPerson', function ($pq) use ($startPlaceId) {
                    $pq->where('start_place_id', (int)$startPlaceId)
                        ->where('price_per_person', '>', 0);
                });
            }
            return $q->take($limit)->get()->map($mapEventTemplate);
        };

        $random_one_day = $fetchByDuration('=', 1, 8);
        $random_one_day_mobile = $fetchByDuration('=', 1, 3);
        $random_two_day = $fetchByDuration('=', 2, 8);
        $random_three_day = $fetchByDuration('=', 3, 8);
        $random_four_day = $fetchByDuration('=', 4, 8);
        $random_five_day = $fetchByDuration('=', 5, 8);
        $random_six_day = $fetchByDuration('>=', 6, 8);

        $destinations = collect();
        $regions = collect();
        $destinationModel = 'App\\Models\\Destination';
        $regionModel = 'App\\Models\\Region';
        if (class_exists($destinationModel)) {
            $sortKey = $this->buildPolishSortKeySql('name');
            $destinations = $destinationModel::orderByRaw("$sortKey ASC, LOWER(name) ASC")->get();
        }
        if (class_exists($regionModel)) {
            $sortKey = $this->buildPolishSortKeySql('name');
            $regions = $regionModel::orderByRaw("$sortKey ASC, LOWER(name) ASC")->get();
        }

        $lengths = \App\Models\EventTemplate::select('duration_days')
            ->whereNotNull('duration_days')
            ->distinct()
            ->orderBy('duration_days', 'asc')
            ->get()
            ->map(function ($template) {
                return (object)[
                    'id' => $template->duration_days,
                    'name' => $template->duration_days == 1 ? '1 dzień' : $template->duration_days . ' dni'
                ];
            });

        $query = \App\Models\EventTemplate::query()
            ->where('is_active', true)
            ->with([
                'tags',
                'startingPlaceAvailabilities',
                'pricesPerPerson.eventTemplateQty',
                'pricesPerPerson.currency',
                'pricesPerPerson.startPlace'
            ]);
        if ($form_name) {
            $search = $this->removeDiacritics(mb_strtolower($form_name));
            $query->where(function ($q) use ($search) {
                $q->whereRaw("LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(name, 'ą', 'a'), 'ć', 'c'), 'ę', 'e'), 'ł', 'l'), 'ń', 'n'), 'ó', 'o'), 'ś', 's'), 'ż', 'z'), 'ź', 'z')) LIKE ?", ['%' . $search . '%']);
            });
        }
        if ($form_min_price) {
            // Filtrowanie po cenie zostanie wykonane po obliczeniu cen w mapEventTemplate
            // $query->where('price', '>', $form_min_price);
        }
        if ($form_max_price) {
            // Filtrowanie po cenie zostanie wykonane po obliczeniu cen w mapEventTemplate
            // $query->where('price', '<', $form_max_price);
        }
        if ($form_destination_id) {
            $query->where('destination_id', $form_destination_id);
        }
        if ($form_length_id) {
            $query->where('length_id', $form_length_id);
        }
        // Temporarily disabled region filtering as all packages have region_id = null
        // if ($region_id) {
        //     $query->where('region_id', $region_id);
        // }
        if ($event_type_id) {
            $query->whereHas('eventTypes', function ($q) use ($event_type_id) {
                $q->where('event_types.id', $event_type_id);
            });
        }

        // Dodaj filtrowanie po miejscu startowym
        if ($currentStartPlaceId) {
            $query->whereRaw(
                "EXISTS (\n                    SELECT 1 FROM event_template_starting_place_availability a\n                    WHERE a.event_template_id = event_templates.id\n                      AND a.start_place_id = ?\n                      AND a.available = 1\n                      AND a.id = (\n                        SELECT MAX(id) FROM event_template_starting_place_availability\n                        WHERE event_template_id = event_templates.id AND start_place_id = ?\n                      )\n                )",
                [(int)$currentStartPlaceId, (int)$currentStartPlaceId]
            )->whereHas('pricesPerPerson', function ($pq) use ($currentStartPlaceId) {
                $pq->where('start_place_id', (int)$currentStartPlaceId)->where('price_per_person', '>', 0);
            });
        }

        // DB-level ordering for name-based sorts and price sorts (computed) to ensure continuity across pages
        if ($sort_by === 'name_asc' || empty($sort_by)) {
            $sortKey = $this->buildPolishSortKeySql('name');
            $query->orderByRaw("$sortKey ASC, LOWER(name) ASC");
        } elseif ($sort_by === 'name_desc') {
            $sortKey = $this->buildPolishSortKeySql('name');
            $query->orderByRaw("$sortKey DESC, LOWER(name) DESC");
        } elseif ($sort_by === 'price_asc' || $sort_by === 'price_desc') {
            $sp = $currentStartPlaceId ? (int)$currentStartPlaceId : null;
            $plnCond = "(UPPER(c.symbol)='PLN' OR UPPER(c.name) LIKE '%ZŁOT%')";
            $spWhere = $sp !== null ? " AND pp.start_place_id = $sp" : "";
            $spWhere2 = $sp !== null ? " AND pp2.start_place_id = $sp" : "";
            $primary = "SELECT MIN( (CAST(((pp.price_per_person + 4) / 5) AS INTEGER)) * 5 )
                                                FROM event_template_price_per_person pp
                                                JOIN currencies c ON c.id = pp.currency_id
                                                JOIN event_template_qties q ON q.id = pp.event_template_qty_id
                                                WHERE pp.event_template_id = event_templates.id
                                                    AND pp.price_per_person > 0
                                                    AND $plnCond
                                                    AND q.qty BETWEEN 40 AND 55
                                                    $spWhere
                                                    AND pp.id IN (
                                                        SELECT MAX(pp2.id)
                                                        FROM event_template_price_per_person pp2
                                                        WHERE pp2.event_template_id = event_templates.id
                                                            AND pp2.price_per_person > 0
                                                            $spWhere2
                                                        GROUP BY pp2.event_template_qty_id
                                                    )";
            $fallback = "SELECT MIN( (CAST(((pp.price_per_person + 4) / 5) AS INTEGER)) * 5 )
                                                FROM event_template_price_per_person pp
                                                JOIN currencies c ON c.id = pp.currency_id
                                                WHERE pp.event_template_id = event_templates.id
                                                    AND pp.price_per_person > 0
                                                    AND $plnCond
                                                    $spWhere
                                                    AND pp.id IN (
                                                        SELECT MAX(pp2.id)
                                                        FROM event_template_price_per_person pp2
                                                        WHERE pp2.event_template_id = event_templates.id
                                                            AND pp2.price_per_person > 0
                                                            $spWhere2
                                                        GROUP BY pp2.event_template_qty_id
                                                    )";
            $computedExpr = "COALESCE(($primary), ($fallback))";
            $query->addSelect(DB::raw("event_templates.* , ($computedExpr) AS computed_price_sql"));
            $query->orderByRaw('(computed_price_sql IS NULL) ASC');
            $query->orderBy('computed_price_sql', $sort_by === 'price_asc' ? 'asc' : 'desc');
            // Tie-breaker po nazwie z polską kolacją
            $sortKey = $this->buildPolishSortKeySql('name');
            $query->orderByRaw("$sortKey ASC, LOWER(name) ASC");
        }
        $packages = $query->paginate(12);
        $packages->getCollection()->transform($mapEventTemplate);
        if (config('app.debug') && $currentStartPlaceId) {
            try {
                Log::debug('directorypackages(): strict local filter applied', [
                    'start_place_id' => (int)$currentStartPlaceId,
                    'total_after_transform' => $packages->getCollection()->count(),
                    'with_price' => $packages->getCollection()->filter(fn($p) => $p->computed_price !== null)->count(),
                ]);
            } catch (\Throwable $e) {
            }
        }

        // Sortowanie kolekcji — po computed_price (tak jak na liście ofert)
        if ($sort_by === 'price_asc' || $sort_by === 'price_desc') {
            // Polegamy na DB-level ORDER BY (computed_price_sql) przed paginacją w celu zachowania ciągłości.
        } elseif ($sort_by === 'name_asc') {
            $collection = $packages->getCollection()->sort(function ($a, $b) {
                return $this->compareNamesLocaleAware($a->name, $b->name);
            })->values();
            $packages->setCollection($collection);
        } elseif ($sort_by === 'name_desc') {
            $collection = $packages->getCollection()->sort(function ($a, $b) {
                return $this->compareNamesLocaleAware($b->name, $a->name);
            })->values();
            $packages->setCollection($collection);
        } else {
            // Domyślne sortowanie alfabetyczne z polskimi znakami
            $collection = $packages->getCollection()->sort(function ($a, $b) {
                return $this->compareNamesLocaleAware($a->name, $b->name);
            })->values();
            $packages->setCollection($collection);
        }

        $regionSlugParam = $request->route('regionSlug') ?? 'region';
        $lengthButtonUrl = function (string $lengthKey) use ($regionSlugParam, $currentStartPlaceId, $request) {
            $query = collect($request->query())
                ->except(['page', 'length_id'])
                ->toArray();

            $query['length_id'] = $lengthKey === '6plus' ? '6plus' : (int) $lengthKey;

            if ($currentStartPlaceId) {
                $query['start_place_id'] = (int) $currentStartPlaceId;
            }

            return route('directory-packages', array_merge([
                'regionSlug' => $regionSlugParam,
            ], $query));
        };

        return view('front.directory-packages', compact(
            'random_six_day',
            'random_five_day',
            'random_four_day',
            'random_three_day',
            'random_two_day',
            'random_one_day_mobile',
            'random_one_day',
            'destinations',
            'regions',
            'lengths',
            'packages',
            'form_name',
            'form_min_price',
            'form_max_price',
            'form_destination_id',
            'region_id',
            'form_length_id',
            'startPlaces',
            'currentStartPlaceId',
            'sort_by',
            'event_type_id',
            'eventTypes',
            'lengthButtonUrl'
        ));
    }
    public function home(Request $request)
    {
        // Lista dostępnych miejsc startowych (jak w packages())
        $startPlaces = $this->getCachedStartPlaces();

        // Kolejność ustalania start_place_id: route slug -> explicit query -> cookie -> (brak domyślnego na home)
        $start_place_id = null;
        $regionSlug = $request->route('regionSlug');
        if ($regionSlug && $regionSlug !== 'region') {
            $place = $startPlaces->first(function ($pl) use ($regionSlug) {
                return str()->slug($pl->name) === $regionSlug;
            });
            if ($place) {
                $start_place_id = $place->id;
                Cookie::queue('start_place_id', (string)$start_place_id, 60 * 24 * 365);
                // diagnostyka: który Place został dopasowany przez regionSlug
                try {
                    Log::info("home: regionSlug={$regionSlug}, matched_place_id={$place->id}, name={$place->name}");
                } catch (\Throwable $e) {
                }
            }
        }
        if (!$start_place_id) {
            $start_place_id = $request->get('start_place_id');
        }
        if (!$start_place_id) {
            $start_place_id = $request->cookie('start_place_id');
        }
        // Walidacja: jeśli wybrane id nie jest na liście dostępnych – ignoruj
        if ($start_place_id && !$startPlaces->where('id', (int)$start_place_id)->first()) {
            $start_place_id = null;
        }

        $durations = [
            (object)['id' => 1, 'name' => '1 dzień'],
            (object)['id' => 2, 'name' => '2 dni'],
            (object)['id' => 3, 'name' => '3 dni'],
            (object)['id' => 5, 'name' => '5 dni'],
            (object)['id' => 7, 'name' => '7 dni'],
        ];

        // Pobierz (max 12) aktywnych EventTemplates, opcjonalnie przefiltrowanych po dostępności dla start_place_id
        $carouselQuery = EventTemplate::where('is_active', true)
            ->with([
                'startingPlaceAvailabilities',
                'pricesPerPerson.eventTemplateQty',
                'pricesPerPerson.currency',
                'transportTypes',
            ]);
        if ($start_place_id) {
            $carouselQuery->whereHas('startingPlaceAvailabilities', function ($q) use ($start_place_id) {
                $q->where('start_place_id', $start_place_id)->where('available', true)->limit(1);
            });
        }
        $random = $carouselQuery->latest('id')
            ->take(12)
            ->get()
            ->map(function ($eventTemplate) use ($start_place_id) {
                $eventTemplate->featured_photo = $eventTemplate->featured_image ? basename($eventTemplate->featured_image) : 'default.png';
                $eventTemplate->length = (object) [
                    'id' => $eventTemplate->duration_days,
                    'name' => $eventTemplate->duration_days == 1 ? '1 dzień' : $eventTemplate->duration_days . ' dni'
                ];
                if ($eventTemplate->relationLoaded('pricesPerPerson')) {
                    $all = $eventTemplate->pricesPerPerson;
                    // Prefer local prices for selected start place; otherwise any >0
                    $candidate = $start_place_id
                        ? $all->filter(fn($p) => $p->price_per_person > 0 && (int)$p->start_place_id === (int)$start_place_id)
                        : $all->filter(fn($p) => $p->price_per_person > 0);
                    // Prefer group 40–55 like on listings
                    $min = $this->resolveMinPlnFromPrices($all, $start_place_id, 40, 55);
                    if ($min === null) {
                        $min = $this->resolveMinPlnFromPrices($all, $start_place_id, null, null);
                    }
                    $eventTemplate->computed_price = $min !== null ? (float)$min : null;
                    $eventTemplate->setRelation('pricesPerPerson', $candidate->values());
                } else {
                    $eventTemplate->computed_price = null;
                }
                return $eventTemplate;
            });
        $random_chunks = $random->chunk(4);

        $sortKey = $this->buildPolishSortKeySql('name');
        $eventTypes = \App\Models\EventType::orderByRaw("$sortKey ASC, LOWER(name) ASC")->get();

        // Pobierz najnowsze aktywne posty blogowe (do 3)
        $blogOrderExpression = DB::raw('COALESCE(published_at, created_at)');
        $blogPosts = BlogPost::published()
            ->orderByDesc($blogOrderExpression)
            ->orderByDesc('id')
            ->take(3)
            ->get();

        $countries = Place::query()
            ->where('is_country', true)
            ->orderBy('name', 'asc')
            ->get();

        return view('front.home', [
            'startPlaces' => $startPlaces,
            'start_place_id' => $start_place_id,
            'durations' => collect($durations),
            'random_chunks' => $random_chunks,
            'blogPosts' => $blogPosts,
            'eventTypes' => $eventTypes,
            'countries' => $countries,
            'trip_types' => $eventTypes,
            // Ensure view has sliders variable even if slider section is commented out or feature disabled
            'sliders' => collect(),
        ]);
    }

    public function packages(Request $request)
    {
        $requiresToken = $request->boolean('requires_turnstile');
        if (!$requiresToken && $request->filled('cf-turnstile-response')) {
            $requiresToken = true;
        }

        $turnstileOk = $this->verifyTurnstileToken($request, 'packages_filter', $requiresToken);
        if ($requiresToken && !$turnstileOk) {
            $regionSlug = $request->route('regionSlug') ?? 'region';
            return redirect()->route('packages', ['regionSlug' => $regionSlug])->with('error', 'Nie udało się zweryfikować zabezpieczenia. Spróbuj ponownie.');
        }

        // Pobierz prawdziwe dane z bazy
        $length_id = request('length_id');
        $sort_by = request('sort_by') ?: 'name_asc';
        $event_type_id = request('event_type_id');
        $destination_name = request('destination_name'); // przesunięte wyżej aby użyć w zapytaniu DB
        $transport_type_id = request('transport_type_id');
        $transportTypeIds = [];
        if ($transport_type_id !== null && $transport_type_id !== '') {
            $transportTypeIds = array_filter(array_map('intval', (array) $transport_type_id));
        }

        // Uproszczona i deterministyczna rezolucja start_place_id:
        // 1) route slug (regionSlug)
        // 2) explicit query param start_place_id
        // 3) cookie start_place_id
        $start_place_id = null;
        $regionSlug = request()->route('regionSlug');
        if ($regionSlug && $regionSlug !== 'region') {
            $place = $this->getCachedStartPlaces()->first(function ($pl) use ($regionSlug) {
                return str()->slug($pl->name) === $regionSlug;
            });
            if ($place) {
                $start_place_id = $place->id;
            }
        }
        if (!$start_place_id) {
            $paramId = request('start_place_id');
            if ($paramId) {
                // Prefer canonical Place when possible, but accept param if there are availability rows
                $p = Place::find($paramId);
                if (($p && $p->starting_place) || \App\Models\EventTemplateStartingPlaceAvailability::where('start_place_id', $paramId)->exists()) {
                    $start_place_id = (int)$paramId;
                }
            }
        }
        if (!$start_place_id) {
            $cookieId = request()->cookie('start_place_id');
            if ($cookieId && ($p = Place::find($cookieId)) && $p->starting_place) {
                $start_place_id = (int)$cookieId;
            }
        }
        if ($start_place_id) {
            Cookie::queue('start_place_id', (string)$start_place_id, 60 * 24 * 365);
        }
        // Diagnostyka: jeśli włączony debug – zaloguj rozstrzygnięty start_place_id
        if (config('app.debug')) {
            try {
                Log::debug('packages(): resolved start_place_id=' . var_export($start_place_id, true));
            } catch (\Throwable $e) {
            }
        }

        // Optional tag filter (slug of tag name) from query param ?tag=
        $tagSlug = request()->get('tag');
        $tagId = null;
        if ($tagSlug) {
            $tag = $this->getCachedTags()->first(function ($t) use ($tagSlug) {
                return str()->slug($t->name) === $tagSlug;
            });
            if ($tag) $tagId = $tag->id;
        }

        // Pobierz unikalne start_place_id z event_template_starting_place_availability
        $startPlaces = $this->getCachedStartPlaces();

        // Śledzenie czy użyto domyślnej wartości Warszawa
        $usedDefaultWarszawa = false;

        // Jeśli nie ma wybranego start_place_id w URL lub jest pusty, sprawdź cookie
        if (!$start_place_id || !$startPlaces->where('id', $start_place_id)->first()) {
            $warszawaPlace = $startPlaces->firstWhere('name', 'Warszawa');
            if ($warszawaPlace) {
                $start_place_id = $warszawaPlace->id;
                $usedDefaultWarszawa = true;
            }
        }

        // Pobierz wszystkie Event Types dla filtra
        $eventTypes = $this->getCachedEventTypes();
        $transportTypes = $this->getCachedTransportTypes();
        $allTags = $this->getCachedTags();

        // Ustal slug regionu do wykorzystania w linkach/JS (na podstawie wybranego miejsca)
        $current_region_slug = 'region';
        if ($start_place_id) {
            $placeName = optional(Place::find($start_place_id))->name;
            if ($placeName) {
                $current_region_slug = str()->slug($placeName);
            }
        }







        $eventTemplate = EventTemplate::where('is_active', true)
            ->with([
                'tags',
                'programPoints',
                'startingPlaceAvailabilities.startPlace',
                'eventTypes',
                'transportTypes',
                'pricesPerPerson.eventTemplateQty',
                'pricesPerPerson.currency',
                'pricesPerPerson.startPlace', // potrzebne do fallbacku po slug
            ])
            ->when($length_id, function ($query) use ($length_id) {
                if ($length_id === '6plus') {
                    $query->where('duration_days', '>=', 6);
                } elseif ($length_id) {
                    $query->where('duration_days', $length_id);
                }
            })
            ->when($start_place_id, function ($query) use ($start_place_id) {
                $this->applyStrictLocalAvailabilityFilter($query, (int) $start_place_id);
            })
            ->when($event_type_id, function ($query) use ($event_type_id) {
                $query->whereHas('eventTypes', function ($q) use ($event_type_id) {
                    $q->where('event_types.id', $event_type_id);
                });
            })
            ->when(!empty($transportTypeIds), function ($query) use ($transportTypeIds) {
                $query->whereHas('transportTypes', function ($q) use ($transportTypeIds) {
                    $q->whereIn('transport_types.id', $transportTypeIds);
                });
            })
            ->when($tagId, function ($query) use ($tagId) {
                $query->whereHas('tags', function ($q) use ($tagId) {
                    $q->where('tags.id', $tagId);
                });
            })
            ->when($request->filled('tags'), function ($query) use ($request) {
                // Multi-tag filter (logged-in enhancement): comma-separated names or slugs
                $raw = (array) $request->input('tags');
                $joined = is_array($raw) ? implode(',', $raw) : (string)$raw;
                $parts = collect(explode(',', $joined))
                    ->map(fn($s) => trim((string)$s))
                    ->filter();
                if ($parts->isNotEmpty()) {
                    $ids = $this->getCachedTags()->filter(function ($t) use ($parts) {
                        $slug = str()->slug($t->name);
                        return $parts->contains(function ($p) use ($t, $slug) {
                            return str()->slug($p) === $slug || mb_strtolower($p) === mb_strtolower($t->name);
                        });
                    })->pluck('id')->values()->all();
                    if (!empty($ids)) {
                        // Require ALL selected tags (AND semantics): add a whereHas per tag id
                        foreach ($ids as $tid) {
                            $query->whereHas('tags', function ($q) use ($tid) {
                                $q->where('tags.id', $tid);
                            });
                        }
                    } else {
                        // No matches: return empty
                        $query->whereRaw('0=1');
                    }
                }
            })
            ->when(!$tagId && $tagSlug, function ($query) {
                // tag slug provided but not found -> return empty
                $query->whereRaw('0=1');
            })
            ->when($destination_name, function ($query) use ($destination_name) {
                $term = $this->removeDiacritics(mb_strtolower($destination_name));
                $nameExpr = $this->buildDiacriticsFreeSql('name');
                $tagExpr = $this->buildDiacriticsFreeSql('tags.name');

                $query->where(function ($qq) use ($term, $nameExpr, $tagExpr) {
                    $qq->whereRaw("$nameExpr LIKE ?", ['%' . $term . '%'])
                        ->orWhereHas('tags', function ($tq) use ($term, $tagExpr) {
                            $tq->whereRaw("$tagExpr LIKE ?", ['%' . $term . '%']);
                        });
                });
            });

        // DB-level ordering for name-based sorts to ensure global alphabetical order across pages
        // Obsługa sortowania:
        //  - duration_asc / duration_desc: głównie DB + dodatkowe sortowanie kolekcji (tie-break nazwa)
        //  - name_asc / name_desc: alfabet (diakrytyki zdejmowane w warstwie kolekcji dla spójności)
        //  - price_*: teraz na poziomie DB przez obliczenie computed_price_sql SUBQUERY, aby utrzymać ciągłość między stronami
        if ($sort_by === 'duration_asc') {
            $sortKey = $this->buildPolishSortKeySql('name');
            $eventTemplate->getQuery()->orderBy('duration_days', 'asc')->orderByRaw("$sortKey ASC, LOWER(name) ASC");
        } elseif ($sort_by === 'duration_desc') {
            $sortKey = $this->buildPolishSortKeySql('name');
            $eventTemplate->getQuery()->orderBy('duration_days', 'desc')->orderByRaw("$sortKey ASC, LOWER(name) ASC");
        } elseif ($sort_by === 'name_asc' || empty($sort_by)) {
            $sortKey = $this->buildPolishSortKeySql('name');
            $eventTemplate->getQuery()->orderByRaw("$sortKey ASC, LOWER(name) ASC");
        } elseif ($sort_by === 'name_desc') {
            $sortKey = $this->buildPolishSortKeySql('name');
            $eventTemplate->getQuery()->orderByRaw("$sortKey DESC, LOWER(name) DESC");
        } elseif ($sort_by === 'price_asc' || $sort_by === 'price_desc') {
            // Zbuduj subzapytanie obliczające minimalną cenę PLN (lokalną) wg zasad listy:
            //  - najnowsze per qty (MAX(id) w danej qty)
            //  - preferuj qty 40..55 (PRIMARY), fallback do dowolnej qty (FALLBACK)
            //  - PLN (code/symbol/name)
            //  - lokalna cena dla wybranego start_place_id (jeśli podano), w przeciwnym razie dowolna
            $sp = $start_place_id ? (int)$start_place_id : null;
            $plnCond = "(UPPER(c.symbol)='PLN' OR UPPER(c.name) LIKE '%ZŁOT%')";
            $spWhere = $sp !== null ? " AND pp.start_place_id = $sp" : "";
            $spWhere2 = $sp !== null ? " AND pp2.start_place_id = $sp" : "";

            $primary = "SELECT MIN( (CAST(((pp.price_per_person + 4) / 5) AS INTEGER)) * 5 )
                                                FROM event_template_price_per_person pp
                                                JOIN currencies c ON c.id = pp.currency_id
                                                JOIN event_template_qties q ON q.id = pp.event_template_qty_id
                                                WHERE pp.event_template_id = event_templates.id
                                                    AND pp.price_per_person > 0
                                                    AND $plnCond
                                                    AND q.qty BETWEEN 40 AND 55
                                                    $spWhere
                                                    AND pp.id IN (
                                                        SELECT MAX(pp2.id)
                                                        FROM event_template_price_per_person pp2
                                                        WHERE pp2.event_template_id = event_templates.id
                                                            AND pp2.price_per_person > 0
                                                            $spWhere2
                                                        GROUP BY pp2.event_template_qty_id
                                                    )";

            $fallback = "SELECT MIN( (CAST(((pp.price_per_person + 4) / 5) AS INTEGER)) * 5 )
                                                FROM event_template_price_per_person pp
                                                JOIN currencies c ON c.id = pp.currency_id
                                                WHERE pp.event_template_id = event_templates.id
                                                    AND pp.price_per_person > 0
                                                    AND $plnCond
                                                    $spWhere
                                                    AND pp.id IN (
                                                        SELECT MAX(pp2.id)
                                                        FROM event_template_price_per_person pp2
                                                        WHERE pp2.event_template_id = event_templates.id
                                                            AND pp2.price_per_person > 0
                                                            $spWhere2
                                                        GROUP BY pp2.event_template_qty_id
                                                    )";

            $computedExpr = "COALESCE(($primary), ($fallback))";
            $eventTemplate->getQuery()->addSelect(DB::raw("event_templates.* , ($computedExpr) AS computed_price_sql"));
            // NULL last for both asc & desc
            $eventTemplate->getQuery()->orderByRaw('(computed_price_sql IS NULL) ASC');
            $eventTemplate->getQuery()->orderBy('computed_price_sql', $sort_by === 'price_asc' ? 'asc' : 'desc');
            // tie-breaker po nazwie
            $sortKey = $this->buildPolishSortKeySql('name');
            $eventTemplate->getQuery()->orderByRaw("$sortKey ASC, LOWER(name) ASC");
        }

        $eventTemplate = $eventTemplate->paginate(24); // Paginacja już po wszystkich filtrach

        // UWAGA: sortowanie po cenie musi odbywać się PO wyliczeniu computed_price,
        // dlatego właściwy blok sortowania znajduje się niżej (po mapowaniu kolekcji).

        // Ujednolicenie z widokiem szczegółu: ceny PLN, >0, filtrowane po start_place_id, jedna najnowsza cena na qty + minimalna dla listy
        $plnCurrencyIds = Currency::where(function ($q) {
            $q->where('name', 'like', '%polski%złoty%')
                ->orWhere('name', 'like', '%złoty%polski%')
                ->orWhere('name', '=', 'Polski złoty')
                ->orWhere('name', '=', 'Złoty polski')
                ->orWhere('symbol', '=', 'PLN');
        })->pluck('id')->toArray();

        $collection = $eventTemplate->getCollection()->map(function ($item) use ($start_place_id) {
            $all = $item->pricesPerPerson;
            // For list view prefer price for group size 40-55; fallback to any qty
            $minPln = $this->resolveMinPlnFromPrices($all, $start_place_id, 40, 55);
            if ($minPln === null) {
                $minPln = $this->resolveMinPlnFromPrices($all, $start_place_id, null, null);
            }
            $item->computed_price = $minPln !== null ? (float)$minPln : null;
            // keep relation to candidate prices (local only if start place present)
            if ($start_place_id) {
                $candidate = $all->filter(fn($p) => $p->price_per_person > 0 && (int)$p->start_place_id === (int)$start_place_id)->values();
            } else {
                $candidate = $all->filter(fn($p) => $p->price_per_person > 0)->values();
            }
            $item->setRelation('pricesPerPerson', $candidate);

            if (config('app.debug') && in_array($item->id, [100, 57])) {
                try {
                    Log::debug('packages(): price_diag_simple', [
                        'event_template_id' => $item->id,
                        'requested_start_place_id' => $start_place_id,
                        'local_mode' => (bool)$start_place_id,
                        'candidate_prices' => $candidate->map(fn($p) => [$p->id, $p->start_place_id, $p->event_template_qty_id, $p->price_per_person])->values(),
                        'computed_price' => $item->computed_price,
                    ]);
                } catch (\Throwable $e) {
                }
            }
            return $item;
        });
        // Defensive: if start_place_id was provided make sure we didn't accidentally keep templates
        // that lack either availability or a local price (some data permutations may slip past DB-level filters)
        if ($start_place_id) {
            $removed = [];
            $collection = $collection->filter(function ($item) use ($start_place_id, &$removed) {
                // Lokalna cena: istnieje któraś zapisana cena z tym start_place_id
                $hasLocalPrice = $item->pricesPerPerson && $item->pricesPerPerson->first(function ($p) use ($start_place_id) {
                    return (int)$p->start_place_id === (int)$start_place_id;
                });
                // Dostępność: uwzględniamy jedynie najnowszy wpis availability dla danego miejsca
                $latestAv = null;
                if ($item->startingPlaceAvailabilities && $item->startingPlaceAvailabilities->count()) {
                    $latestAv = $item->startingPlaceAvailabilities
                        ->where('start_place_id', $start_place_id)
                        ->where('end_place_id', $item->start_place_id)
                        ->sortByDesc('id')
                        ->first();
                }
                $hasAvailability = $latestAv && $latestAv->available;
                $ok = $hasLocalPrice && $hasAvailability;
                if (!$ok) $removed[] = $item->id ?? null;
                return $ok;
            })->values();
            if (config('app.debug')) {
                try {
                    Log::debug('packages(): defensive strict-local filter removed items', ['start_place_id' => $start_place_id, 'removed_ids' => $removed]);
                } catch (\Throwable $e) {
                }
            }
        }
        $eventTemplate->setCollection($collection);

        // Teraz, gdy computed_price jest ustawione, zastosuj sortowanie zgodnie z sort_by.
        if ($sort_by === 'duration_asc') {
            $sorted = $eventTemplate->getCollection()->sort(function ($a, $b) {
                $ad = (int)($a->duration_days ?? 0);
                $bd = (int)($b->duration_days ?? 0);
                if ($ad === $bd) {
                    return $this->compareNamesLocaleAware($a->name, $b->name);
                }
                return $ad <=> $bd;
            })->values();
            $eventTemplate->setCollection($sorted);
        } elseif ($sort_by === 'duration_desc') {
            $sorted = $eventTemplate->getCollection()->sort(function ($a, $b) {
                $ad = (int)($a->duration_days ?? 0);
                $bd = (int)($b->duration_days ?? 0);
                if ($ad === $bd) {
                    return $this->compareNamesLocaleAware($a->name, $b->name);
                }
                return $bd <=> $ad;
            })->values();
            $eventTemplate->setCollection($sorted);
        } elseif ($sort_by === 'price_asc' || $sort_by === 'price_desc') {
            // Dla sortowania ceną polegamy na DB-level ORDER BY (computed_price_sql) przed paginacją,
            // aby zachować ciągłość między stronami. Nie zmieniamy kolejności w obrębie strony.
        } elseif ($sort_by === 'name_asc') {
            $sorted = $eventTemplate->getCollection()->sort(function ($a, $b) {
                return $this->compareNamesLocaleAware($a->name, $b->name);
            })->values();
            $eventTemplate->setCollection($sorted);
        } elseif ($sort_by === 'name_desc') {
            $sorted = $eventTemplate->getCollection()->sort(function ($a, $b) {
                return $this->compareNamesLocaleAware($b->name, $a->name);
            })->values();
            $eventTemplate->setCollection($sorted);
        } else {
            // Domyślnie jak name_asc
            $sorted = $eventTemplate->getCollection()->sort(function ($a, $b) {
                return $this->compareNamesLocaleAware($a->name, $b->name);
            })->values();
            $eventTemplate->setCollection($sorted);
        }

        // Dodatkowe post-filtrowanie niepotrzebne – warunki zostały przeniesione do zapytania.

        // Jeśli żądanie infinite scroll / partial=1 i oczekiwany JSON — TERAZ po obliczeniach
        if (request()->get('partial') == '1' && request()->wantsJson()) {
            $html = view('front.partials.packages-items', [
                'eventTemplate' => $eventTemplate->getCollection(),
                'requestedQty' => request('qty') ? (int) request('qty') : null,
                'start_place_id' => $start_place_id ?? null,
            ])->render();
            return response()->json([
                'html' => $html,
                'next_page' => $eventTemplate->currentPage() < $eventTemplate->lastPage() ? $eventTemplate->currentPage() + 1 : null,
                'has_more' => $eventTemplate->currentPage() < $eventTemplate->lastPage(),
                'current_page' => $eventTemplate->currentPage(),
                'total' => $eventTemplate->total(),
            ]);
        }

        return view('front.packages', [
            'eventTemplate' => $eventTemplate,
            'startPlaces' => $startPlaces,
            'start_place_id' => $start_place_id,
            'eventTypes' => $eventTypes,
            'event_type_id' => $event_type_id,
            'transportTypes' => $transportTypes,
            'transport_type_id' => $transport_type_id,
            'allTags' => $allTags,
            'length_id' => $length_id,
            'sort_by' => $sort_by,
            'usedDefaultWarszawa' => $usedDefaultWarszawa,
            'current_region_slug' => $current_region_slug,
        ]);
    }

    /**
     * Partial HTML with filtered packages list for live search (AJAX)
     */
    public function packagesPartial(Request $request)
    {
        $length_id = $request->get('length_id');
        $sort_by = $request->get('sort_by') ?: 'name_asc';
        $start_place_id = $request->get('start_place_id');
        $event_type_id = $request->get('event_type_id');
        $destination_name = $request->get('destination_name');
        $min_price = $request->get('min_price');
        $max_price = $request->get('max_price');
        $transport_type_id = $request->get('transport_type_id');
        $transportTypeIds = [];
        if ($transport_type_id !== null && $transport_type_id !== '') {
            $transportTypeIds = array_filter(array_map('intval', (array) $transport_type_id));
        }
        // Upraszczamy: ignorujemy dopasowanie do konkretnej ilości – tylko minimalna cena PLN.
        $qtyRequested = null;

        // Fallback dla start_place_id: route slug -> cookie -> domyślnie Warszawa
        if (!$start_place_id) {
            $regionSlug = $request->route('regionSlug');
            if ($regionSlug && $regionSlug !== 'region') {
                $place = $this->getCachedStartPlaces()->first(function ($pl) use ($regionSlug) {
                    return str()->slug($pl->name) === $regionSlug;
                });
                if ($place) {
                    $start_place_id = $place->id;
                }
            }
        }
        if (!$start_place_id) {
            $cookieId = $request->cookie('start_place_id');
            if ($cookieId) {
                $p = Place::find($cookieId);
                if (($p && $p->starting_place) || \App\Models\EventTemplateStartingPlaceAvailability::where('start_place_id', $cookieId)->exists()) {
                    $start_place_id = (int)$cookieId;
                }
            }
        }
        if (!$start_place_id) {
            $warszawa = Place::where('starting_place', true)->where('name', 'Warszawa')->first();
            if ($warszawa) $start_place_id = $warszawa->id;
        }

        $eventTemplate = EventTemplate::where('is_active', true)
            ->with([
                'tags',
                'programPoints',
                'startingPlaceAvailabilities.startPlace',
                'eventTypes',
                'transportTypes',
                // ceny za osobę + warianty qty do wyliczeń
                'pricesPerPerson.eventTemplateQty',
                'pricesPerPerson.currency',
            ])
            ->when($length_id, function ($query) use ($length_id) {
                if ($length_id === '6plus') {
                    $query->where('duration_days', '>=', 6);
                } elseif ($length_id) {
                    $query->where('duration_days', $length_id);
                }
            })
            ->when($start_place_id, function ($query) use ($start_place_id) {
                $this->applyStrictLocalAvailabilityFilter($query, (int) $start_place_id);
            })
            ->when($event_type_id, function ($query) use ($event_type_id) {
                $query->whereHas('eventTypes', function ($q) use ($event_type_id) {
                    $q->where('event_types.id', $event_type_id);
                });
            })
            ->when(!empty($transportTypeIds), function ($query) use ($transportTypeIds) {
                $query->whereHas('transportTypes', function ($q) use ($transportTypeIds) {
                    $q->whereIn('transport_types.id', $transportTypeIds);
                });
            })
            ->limit(24) // Limit results to prevent memory exhaustion
            ->get();

        if ($destination_name) {
            $search = $this->removeDiacritics(mb_strtolower($destination_name));
            $eventTemplate = $eventTemplate->filter(function ($item) use ($search) {
                $name = $this->removeDiacritics(mb_strtolower($item->name));
                $nameMatch = strpos($name, $search) !== false;
                $tagMatch = $item->tags && $item->tags->contains(function ($tag) use ($search) {
                    $tagName = $this->removeDiacritics(mb_strtolower($tag->name));
                    return strpos($tagName, $search) !== false;
                });
                return $nameMatch || $tagMatch;
            })->values();
        }

        // Oblicz computed_price = minimalna cena PLN dla wybranego start_place_id (fallback global)
        $plnCurrencyIds = Currency::where(function ($q) {
            $q->where('name', 'like', '%polski%złoty%')
                ->orWhere('name', 'like', '%złoty%polski%')
                ->orWhere('name', '=', 'Polski złoty')
                ->orWhere('name', '=', 'Złoty polski')
                ->orWhere('symbol', '=', 'PLN');
        })->pluck('id')->toArray();
        $eventTemplate = $eventTemplate->map(function ($item) use ($start_place_id) {
            $all = $item->pricesPerPerson;
            // For partial/listing responses prefer 40-55 group
            $minPln = $this->resolveMinPlnFromPrices($all, $start_place_id, 40, 55);
            if ($minPln === null) {
                $minPln = $this->resolveMinPlnFromPrices($all, $start_place_id, null, null);
            }
            $item->computed_price = $minPln !== null ? (float)$minPln : null;
            // keep relation to candidate prices (local only if start place present)
            if ($start_place_id) {
                $candidate = $all->filter(fn($p) => $p->price_per_person > 0 && (int)$p->start_place_id === (int)$start_place_id)->values();
            } else {
                $candidate = $all->filter(fn($p) => $p->price_per_person > 0)->values();
            }
            $item->setRelation('pricesPerPerson', $candidate);

            if (config('app.debug') && isset($item->id) && (int)$item->id === 57) {
                try {
                    Log::debug('packagesPartial(): price_diag', [
                        'event_template_id' => $item->id,
                        'start_place_id' => $start_place_id,
                        'candidate_prices' => $candidate->map(fn($p) => [$p->id, $p->start_place_id, $p->event_template_qty_id, $p->price_per_person, $p->currency?->code ?? null])->values(),
                        'computed_price' => $item->computed_price,
                    ]);
                } catch (\Throwable $e) {
                }
            }
            return $item;
        });

        // Usuń eventy bez cen dla wybranego start_place_id
        if ($start_place_id) {
            // Po zmianach query powinno już gwarantować lokalną cenę + availability, ale pozostawiamy zabezpieczenie.
            $eventTemplate = $eventTemplate->filter(function ($item) use ($start_place_id) {
                $hasLocalPrice = $item->pricesPerPerson && $item->pricesPerPerson->first(function ($p) use ($start_place_id) {
                    return (int)$p->start_place_id === (int)$start_place_id;
                });
                $hasAvailability = $item->startingPlaceAvailabilities && $item->startingPlaceAvailabilities->first(function ($av) use ($start_place_id, $item) {
                    return (int)$av->start_place_id === (int)$start_place_id
                        && (int)$av->end_place_id === (int)$item->start_place_id
                        && $av->available;
                });
                return $hasLocalPrice && $hasAvailability;
            })->values();
        }
        if (config('app.debug') && $start_place_id) {
            try {
                Log::debug('packagesPartial(): strict local filter applied', ['start_place_id' => (int)$start_place_id, 'count' => $eventTemplate->count()]);
            } catch (\Throwable $e) {
            }
        }

        // Filtr po zakresie cen, jeśli podano min/max
        if ($min_price !== null && $min_price !== '') {
            $minP = (float) $min_price;
            $eventTemplate = $eventTemplate->filter(function ($item) use ($minP) {
                return $item->computed_price !== null ? ($item->computed_price >= $minP) : true;
            })->values();
        }
        if ($max_price !== null && $max_price !== '') {
            $maxP = (float) $max_price;
            $eventTemplate = $eventTemplate->filter(function ($item) use ($maxP) {
                return $item->computed_price !== null ? ($item->computed_price <= $maxP) : true;
            })->values();
        }

        // Sorting
        if ($sort_by === 'duration_asc') {
            $eventTemplate = $eventTemplate->sort(function ($a, $b) {
                $ad = (int)($a->duration_days ?? 0);
                $bd = (int)($b->duration_days ?? 0);
                if ($ad === $bd) {
                    return $this->compareNamesLocaleAware($a->name, $b->name);
                }
                return $ad <=> $bd;
            })->values();
        } elseif ($sort_by === 'duration_desc') {
            $eventTemplate = $eventTemplate->sort(function ($a, $b) {
                $ad = (int)($a->duration_days ?? 0);
                $bd = (int)($b->duration_days ?? 0);
                if ($ad === $bd) {
                    return $this->compareNamesLocaleAware($a->name, $b->name);
                }
                return $bd <=> $ad;
            })->values();
        } elseif ($sort_by === 'price_asc') {
            $eventTemplate = $eventTemplate->sort(function ($a, $b) {
                $aPrice = is_numeric($a->computed_price) ? (float)$a->computed_price : INF;
                $bPrice = is_numeric($b->computed_price) ? (float)$b->computed_price : INF;
                return $aPrice <=> $bPrice;
            })->values();
        } elseif ($sort_by === 'price_desc') {
            $eventTemplate = $eventTemplate->sort(function ($a, $b) {
                $aPrice = is_numeric($a->computed_price) ? (float)$a->computed_price : -INF;
                $bPrice = is_numeric($b->computed_price) ? (float)$b->computed_price : -INF;
                return $bPrice <=> $aPrice;
            })->values();
        } elseif ($sort_by === 'name_asc') {
            $eventTemplate = $eventTemplate->sort(function ($a, $b) {
                return $this->compareNamesLocaleAware($a->name, $b->name);
            })->values();
        } elseif ($sort_by === 'name_desc') {
            $eventTemplate = $eventTemplate->sort(function ($a, $b) {
                return $this->compareNamesLocaleAware($b->name, $a->name);
            })->values();
        } else {
            // Domyślnie jak name_asc
            $eventTemplate = $eventTemplate->sort(function ($a, $b) {
                return $this->compareNamesLocaleAware($a->name, $b->name);
            })->values();
        }

        return view('front.partials.packages-list', [
            'eventTemplate' => $eventTemplate,
            'requestedQty' => null,
            'start_place_id' => $start_place_id,
        ]);
    }

    public function package($slug)
    {
        $eventTemplate = EventTemplate::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();
        // Determine start place only from cookie now (no query param kept)
        $startPlaceId = request()->cookie('start_place_id');
        return redirect()->to($eventTemplate->prettyUrl($startPlaceId ? (int)$startPlaceId : null), 301);
    }

    public function packagePretty($regionSlug, $dayLength, $id, $slug)
    {
        $eventTemplate = EventTemplate::where('id', $id)
            ->where('is_active', true)
            ->with(['tags', 'programPoints', 'transportTypes'])
            ->firstOrFail();

        // Always resolve start_place_id from regionSlug BUT only among starting_place = true (standaryzacja)
        $startPlaceId = $this->resolveStartPlaceIdFromSlug($regionSlug);

        // If not found, fallback to null (no cookie fallback, strict URL-based)

        // Optionally, redirect if regionSlug does not match canonical slug for selected place
        $expectedRegion = $startPlaceId ? str()->slug(optional(Place::find($startPlaceId))->name) : 'region';
        $expectedDay = ($eventTemplate->duration_days ?? 0) . '-dniowe';
        $expectedSlug = $eventTemplate->slug;
        if ($regionSlug !== $expectedRegion || $dayLength !== $expectedDay || $slug !== $expectedSlug) {
            return redirect()->to($eventTemplate->prettyUrl($startPlaceId ? (int)$startPlaceId : null), 301);
        }

        if ($startPlaceId && ! $this->hasStrictLocalAvailabilityForTemplate($eventTemplate, (int) $startPlaceId)) {
            return $this->redirectToRegionalOffers((string) $regionSlug);
        }

        // Pricing logic (PLN only, filtered by start_place_id)
        $plnCurrencyIds = Currency::where(function ($q) {
            $q->where('name', 'like', '%polski%złoty%')
                ->orWhere('name', 'like', '%złoty%polski%')
                ->orWhere('name', '=', 'Polski złoty')
                ->orWhere('name', '=', 'Złoty polski')
                ->orWhere('symbol', '=', 'PLN');
        })->pluck('id')->toArray();

        // Load ALL prices for this template (all currencies, all start_places) like in home method
        $allPricesQuery = EventTemplatePricePerPerson::with(['eventTemplateQty', 'currency', 'startPlace'])
            ->where('event_template_id', $eventTemplate->id)
            ->where('price_per_person', '>', 0)
            ->orderBy('event_template_qty_id')
            ->orderByDesc('id')
            ->get();

        // Pełny zestaw cen dla tego eventu (wszystkie waluty, wszystkie start_places)
        $allPricesForStart = $allPricesQuery->values();

        // Zgrupowana, jedna cena na qty (najnowsza) — używana do niektórych wyliczeń
        $groupedPrices = $allPricesForStart->groupBy('event_template_qty_id')
            ->map(fn($group) => $group->first())
            ->values();
        // Tymczasowe logowanie diagnostyczne
        try {
            Log::info("packagePretty: event_template_id={$eventTemplate->id}, resolved_start_place_id=" . ($startPlaceId ?? 'null') . ", prices_found=" . $allPricesForStart->count());
            $ids = $allPricesForStart->pluck('start_place_id')->unique()->values()->toArray();
            Log::info('packagePretty: start_place_ids_in_prices=' . json_encode($ids));
        } catch (\Throwable $e) {
            // ignore logging failures
        }
        // Ustaw relację główną na pełny zestaw cen, by widok szczegółu widział wszystkie waluty
        $eventTemplate->setRelation('pricesPerPerson', $allPricesForStart);
        // Dodatkowo przechowaj zgrupowane ceny, jeśli trzeba ich będzie użyć gdzieś indziej
        $eventTemplate->setRelation('pricesPerPersonGrouped', $groupedPrices);

        // Zgodnie z resztą kontrolera ustaw także computed_price (PLN minimal, local-first, latest-per-qty)
        try {
            // packagePretty: keep full behavior (no qty restriction)
            $computed = $this->resolveMinPlnFromPrices($allPricesForStart, $startPlaceId);
            $eventTemplate->computed_price = $computed !== null ? (float)$computed : null;
        } catch (\Throwable $e) {
            $eventTemplate->computed_price = null;
        }

        // Previous / next within same start place availability (by numeric ID)
        $prevPackage = null;
        $nextPackage = null;
        if ($startPlaceId) {
            $prevPackage = EventTemplate::where('is_active', true)
                ->where('id', '<', $eventTemplate->id)
                ->whereHas('startingPlaceAvailabilities', function ($q) use ($startPlaceId) {
                    $q->where('start_place_id', $startPlaceId)->where('available', true)->limit(1);
                })
                ->orderBy('id', 'desc')
                ->first();
            $nextPackage = EventTemplate::where('is_active', true)
                ->where('id', '>', $eventTemplate->id)
                ->whereHas('startingPlaceAvailabilities', function ($q) use ($startPlaceId) {
                    $q->where('start_place_id', $startPlaceId)->where('available', true)->limit(1);
                })
                ->orderBy('id', 'asc')
                ->first();
        } else {
            // Fallback global (no start place chosen) - only templates with no start place specific pricing required.
            $prevPackage = EventTemplate::where('is_active', true)
                ->where('id', '<', $eventTemplate->id)
                ->orderBy('id', 'desc')
                ->first();
            $nextPackage = EventTemplate::where('is_active', true)
                ->where('id', '>', $eventTemplate->id)
                ->orderBy('id', 'asc')
                ->first();
        }

        $priceRanges = collect($this->buildPriceRangesForPlace($eventTemplate->pricesPerPerson, $startPlaceId));

        return view('front.package', [
            'eventTemplate' => $eventTemplate,
            'item' => $eventTemplate,
            'start_place_id' => $startPlaceId,
            'prevPackage' => $prevPackage,
            'nextPackage' => $nextPackage,
            'priceRanges' => $priceRanges,
        ]);
    }

    public function packagePrettyWord(Request $request, $regionSlug, $dayLength, $id, $slug)
    {
        $payload = $request->validateWithBag('wordOffer', [
            'organization_name' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:100'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'additional_notes' => ['nullable', 'string', 'max:2000'],
            'cf-turnstile-response' => ['nullable', 'string', 'max:2048'],
        ]);

        if (!$this->verifyTurnstileToken($request, 'word_offer_download')) {
            return back()->withErrors([
                'turnstile' => 'Nie udało się potwierdzić zabezpieczenia. Spróbuj ponownie.',
            ], 'wordOffer');
        }

        $eventTemplate = EventTemplate::where('id', $id)
            ->where('is_active', true)
            ->with([
                'tags',
                'programPoints',
                'programPoints.children',
                'pricesPerPerson.eventTemplateQty',
                'pricesPerPerson.currency',
                'pricesPerPerson.startPlace',
                'eventPriceDescription',
            ])
            ->firstOrFail();

        $startPlaceId = $this->resolveStartPlaceIdFromSlug($regionSlug);

        $expectedRegionSlug = $startPlaceId ? (string) str(optional(Place::find($startPlaceId))->name)->slug() : 'region';
        $expectedDayLength = ($eventTemplate->duration_days ?? 0) . '-dniowe';
        $expectedSlug = $eventTemplate->slug;

        if ($regionSlug !== $expectedRegionSlug || $dayLength !== $expectedDayLength || $slug !== $expectedSlug) {
            return redirect()->to($eventTemplate->prettyUrl($startPlaceId ?: null));
        }

        if ($startPlaceId && ! $this->hasStrictLocalAvailabilityForTemplate($eventTemplate, (int) $startPlaceId)) {
            return $this->redirectToRegionalOffers((string) $regionSlug);
        }

        $prices = $eventTemplate->pricesPerPerson ?? collect();
        if ($prices->isEmpty()) {
            $prices = EventTemplatePricePerPerson::with(['eventTemplateQty', 'currency', 'startPlace'])
                ->where('event_template_id', $eventTemplate->id)
                ->get();
            $eventTemplate->setRelation('pricesPerPerson', $prices);
        }

        $priceRanges = $this->buildPriceRangesForPlace($eventTemplate->pricesPerPerson, $startPlaceId);
        $program = $this->extractProgramForWord($eventTemplate);
        $startPlaceName = $startPlaceId ? optional(Place::find($startPlaceId))->name : 'Warszawa';

        if (!$startPlaceName) {
            $startPlaceName = 'Warszawa';
        }

        if ($eventTemplate->relationLoaded('eventPriceDescription')) {
            $priceDescriptionHtml = optional($eventTemplate->eventPriceDescription->first())->description ?? '';
        } else {
            $priceDescriptionHtml = optional($eventTemplate->eventPriceDescription()->first())->description ?? '';
        }

        $orgName = $this->sanitizeWordText($payload['organization_name'] ?? '');
        $contactPerson = $this->sanitizeWordText($payload['contact_person'] ?? '');
        $contactPhone = $this->sanitizeWordText($payload['contact_phone'] ?? '');
        $contactEmail = $this->sanitizeWordText($payload['contact_email'] ?? '');
        $additionalNotes = $this->sanitizeWordText($payload['additional_notes'] ?? '');
        $preparedAt = now();

        Settings::setCompatibility(true);
        Settings::setOutputEscapingEnabled(true);
        if (class_exists('\\ZipArchive')) {
            Settings::setZipClass(Settings::ZIPARCHIVE);
        }

        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Calibri Light');
        $phpWord->setDefaultFontSize(12);

        $sectionStyle = [
            'marginTop' => 1134,
            'marginBottom' => 1134,
            'marginLeft' => 851,
            'marginRight' => 851,
        ];
        $coverSectionStyle = $sectionStyle;
        $coverSectionStyle['vAlign'] = 'center';

        $docTitle = $this->sanitizeWordText($eventTemplate->name ?? 'Oferta');
        $docSubtitle = $this->sanitizeWordText($eventTemplate->subtitle ?? '');
        $titleDisplay = $docTitle !== '' ? $docTitle : 'Oferta';
        $daysDisplay = ($eventTemplate->duration_days ?? null)
            ? $this->sanitizeWordText((string) $eventTemplate->duration_days) . ' dni'
            : '—';
        $startPlaceDisplay = $this->sanitizeWordText($startPlaceName) ?: '—';

        $companyLines = [
            'Organizator: Biuro Podróży RAFA',
            'Ul. Marii Konopnickiej 6, 00-491 Warszawa',
            'tel. +48 606 102 243 • rafa@bprafa.pl',
            'www.bprafa.pl • NIP 716-250-87-61 • Bank Millennium S.A. 10 1160 2202 0000 0002 0065 6958',
        ];

        $coverDetails = [
            'Data przygotowania oferty' => $preparedAt->format('d.m.Y'),
            'Termin ważności oferty' => '21 dni od daty przygotowania oferty',
            'Wyjazd z' => $startPlaceDisplay,
            'Liczba dni' => $daysDisplay,
            'Zamawiający' => $orgName !== '' ? $orgName : '—',
            'Opiekun / nauczyciel' => $contactPerson !== '' ? $contactPerson : '—',
            'Telefon' => $contactPhone !== '' ? $contactPhone : '—',
            'Email' => $contactEmail !== '' ? $contactEmail : '—',
        ];

        $coverSection = $phpWord->addSection($coverSectionStyle);
        $this->configureWordSectionBranding($coverSection);

        $coverSection->addText('Oferta wycieczki', ['size' => 26, 'bold' => true], ['alignment' => 'center']);
        $coverSection->addText($titleDisplay, ['size' => 26, 'bold' => true, 'color' => 'C00000'], ['alignment' => 'center']);
        if ($docSubtitle !== '') {
            $coverSection->addText($docSubtitle, ['size' => 18, 'color' => '444444'], ['alignment' => 'center']);
        }
        $coverSection->addText('Termin: do ustalenia', ['size' => 16, 'bold' => true], ['alignment' => 'center']);

        $coverSection->addTextBreak(1);
        $coverTable = $coverSection->addTable([
            'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER,
            'cellMargin' => 120,
            'width' => 9000,
        ]);
        foreach ($coverDetails as $label => $value) {
            $row = $coverTable->addRow();
            $row->addCell(3500)->addText($label . ':', ['color' => '0070C0', 'bold' => true]);
            $row->addCell(5500)->addText($value, ['bold' => true]);
        }

        $coverSection->addTextBreak(1);
        foreach ($companyLines as $line) {
            $coverSection->addText($line);
        }

        $section = $phpWord->addSection($sectionStyle);
        $this->configureWordSectionBranding($section);

        $section->addText($titleDisplay, ['size' => 20, 'bold' => true, 'color' => 'C00000'], ['alignment' => 'center']);
        if ($docSubtitle !== '') {
            $section->addText($docSubtitle, ['size' => 14, 'color' => '444444'], ['alignment' => 'center']);
        }

        $section->addTextBreak(1);
        $section->addText('Wyjazd z: ' . $startPlaceDisplay);
        $section->addText('Liczba dni: ' . $daysDisplay);

        $section->addTextBreak(1);
        $section->addText('Dane zamawiającego', ['bold' => true, 'size' => 14, 'color' => '0070C0']);
        $section->addText('Nazwa grupy: ' . ($orgName !== '' ? $orgName : '—'));
        $section->addText('Opiekun / nauczyciel: ' . ($contactPerson !== '' ? $contactPerson : '—'));
        $section->addText('Telefon: ' . ($contactPhone !== '' ? $contactPhone : '—'));
        $section->addText('Email: ' . ($contactEmail !== '' ? $contactEmail : '—'));

        $section->addTextBreak(1);
        $section->addText('Dane biura podróży', ['bold' => true, 'size' => 14, 'color' => '0070C0']);
        foreach ($companyLines as $line) {
            $section->addText($line);
        }

        if ($additionalNotes !== '') {
            $section->addTextBreak(1);
            $section->addText('Uwagi dodatkowe', ['bold' => true, 'size' => 14, 'color' => '0070C0']);
            foreach (explode("\n", $additionalNotes) as $noteLine) {
                $noteLine = trim($noteLine);
                if ($noteLine === '') {
                    continue;
                }
                $section->addText($noteLine);
            }
        }

        if (!empty($program)) {
            $section->addTextBreak(1);
            $section->addText('Program wycieczki', ['bold' => true, 'size' => 14, 'color' => '0070C0']);

            foreach ($program as $block) {
                $section->addTextBreak(1);
                $section->addText($this->sanitizeWordText($block['label']), ['bold' => true, 'color' => '0070C0']);
                foreach ($block['points'] as $point) {
                    // Build a list item run so we can mix inline styles (only title may be fully bold)
                    $listRun = $section->addListItemRun(0);
                    $titleStyle = $point['bold'] ? ['bold' => true] : null;
                    $listRun->addText($this->sanitizeWordText($point['title']), $titleStyle);

                    if (!empty($point['description'])) {
                        // separator between title and description (plain text, no inline styles)
                        $listRun->addText(' – ');
                        $listRun->addText($point['description']);
                    }

                    if (!empty($point['children'])) {
                        foreach ($point['children'] as $child) {
                            $childRun = $section->addListItemRun(1);
                            $childTitleStyle = $child['bold'] ? ['bold' => true] : null;
                            $childRun->addText($this->sanitizeWordText($child['title']), $childTitleStyle);
                            if (!empty($child['description'])) {
                                $childRun->addText(' – ');
                                $childRun->addText($child['description']);
                            }
                        }
                    }
                }
            }
        }

        if (!empty($priceRanges)) {
            $section->addTextBreak(1);
            $section->addText('Cennik (PLN – aktualne miejsce wyjazdu)', ['bold' => true, 'size' => 14, 'color' => 'C00000']);

            $table = $section->addTable([
                'borderColor' => 'cccccc',
                'borderSize' => 6,
                'cellMargin' => 80,
                'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER,
            ]);

            $headerRow = $table->addRow();
            $headerRow->addCell(2000, ['valign' => 'center'])->addText('Liczba osób', ['bold' => true, 'color' => '0070C0']);
            $headerRow->addCell(2000, ['valign' => 'center'])->addText('Cena /os. (PLN)', ['bold' => true, 'color' => '0070C0']);
            $headerRow->addCell(3000, ['valign' => 'center'])->addText('Uwagi', ['bold' => true, 'color' => '0070C0']);

            foreach ($priceRanges as $range) {
                $row = $table->addRow();
                $label = $range['from'] === $range['to'] ? $range['from'] . ' osób' : $range['from'] . '–' . $range['to'] . ' osób';
                $row->addCell(2000)->addText($label);
                $row->addCell(2000)->addText(number_format($range['price'], 0, ',', ' ') . ' zł');
                $row->addCell(3000)->addText(!empty($range['other']) ? implode(', ', $range['other']) : '');
            }
        } else {
            $section->addTextBreak(1);
            $section->addText('Brak dostępnych cen w PLN dla wybranego miejsca wyjazdu.', ['italic' => true, 'color' => 'C00000']);
        }

        $section->addTextBreak(1);
        $section->addText('W cenie', ['bold' => true, 'size' => 14, 'color' => '0070C0']);

        if (!empty($priceDescriptionHtml)) {
            $this->appendHtmlSnippetToSection($section, $priceDescriptionHtml);
        } else {
            $section->addText($this->sanitizeWordText('Cena zawiera:'), ['bold' => true]);
            $defaultIncludes = [
                'zakwaterowanie w pokojach z łazienkami',
                'wyżywienie zgodnie z programem (2 śniadania, 2 obiady, 2 kolacje)',
                'przejazd autokarem',
                'opłaty drogowe i parkingowe',
                'opiekę pilota na całej trasie wycieczki',
                'bilety wstępu do zwiedzanych obiektów',
                'realizację programu',
                'przewodników lokalnych',
                'ubezpieczenie NNW uczestników wycieczki do kwoty 10 000 zł/osoba',
                'podatek VAT',
                'miejsca gratis dla opiekunów (1 opiekun na 15 uczestników)',
            ];
            foreach ($defaultIncludes as $line) {
                $section->addListItem($this->sanitizeWordText($line));
            }

            $section->addText($this->sanitizeWordText('Cena nie zawiera:'), ['bold' => true]);
            $defaultExcludes = [
                'wydatków własnych',
                'punktów programu opisanych i proponowanych jako „Fakultatywne”',
            ];
            foreach ($defaultExcludes as $line) {
                $section->addListItem($this->sanitizeWordText($line));
            }
        }

        $section->addTextBreak(1);
        $section->addText('Dodatkowe ubezpieczenie', ['bold' => true, 'size' => 14, 'color' => '0070C0']);
        $insuranceItems = [
            'Ubezpieczenie kosztów rezygnacji: dobrowolne ubezpieczenie zwraca 100% kosztów w przypadku losowej rezygnacji (choroba, wypadek, pożar, śmierć bliskiej osoby). Składka wynosi 3,2% wartości imprezy. Polisę należy wykupić w dniu podpisania umowy lub do 7 dni od zawarcia umowy, jeśli wyjazd rozpoczyna się później niż za 30 dni.',
            'Choroby przewlekłe: osoby cierpiące na choroby przewlekłe powinny rozszerzyć polisę o ryzyko zaostrzenia choroby (dotyczy ubezpieczenia kosztów rezygnacji i kosztów leczenia).',
            'Zwiększenie sumy ubezpieczenia: istnieje możliwość indywidualnego podniesienia sumy ubezpieczenia – prosimy o kontakt z biurem.',
        ];
        foreach ($insuranceItems as $line) {
            $section->addListItem($this->sanitizeWordText($line));
        }

        $section->addTextBreak(1);
        $section->addText('Faktura', ['bold' => true, 'size' => 14, 'color' => '0070C0']);
        $section->addText($this->sanitizeWordText('Aby otrzymać fakturę za udział w wycieczce, prosimy przed rozpoczęciem imprezy o przesłanie danych poprzez formularz na stronie. Faktury wysyłamy drogą elektroniczną po zakończeniu wyjazdu.'));
        $section->addText($this->sanitizeWordText('Wniosek o fakturę na osobę fizyczną: https://bprafa.pl/wniosek-o-fakture-na-osobe-fizyczna/'));
        $section->addText($this->sanitizeWordText('Wniosek o fakturę na firmę: https://bprafa.pl/faktura-na-firme/'));

        $section->addTextBreak(1);
        $section->addText('Dodatkowe informacje', ['bold' => true, 'size' => 14, 'color' => '0070C0']);
        $additionalInfoItems = [
            'Program ma charakter ramowy – kolejność zwiedzania może ulec zmianie.',
            'Na życzenie klienta program można dostosować do indywidualnych potrzeb.',
            'Specjalne diety mogą wiązać się z dodatkowymi opłatami.',
        ];
        foreach ($additionalInfoItems as $line) {
            $section->addListItem($this->sanitizeWordText($line));
        }

        $section->addTextBreak(1);
        $section->addText('Kontakt i zapytania', ['bold' => true, 'size' => 14, 'color' => '0070C0']);
        $section->addText($this->sanitizeWordText('W przypadku pytań lub chęci uzyskania oferty dla innej liczby uczestników napisz do nas na adres rafa@bprafa.pl lub skorzystaj z formularza kontaktowego na stronie wycieczki.'));

        $section->addTextBreak(2);
        $section->addText('Dokument wygenerowany: ' . now()->format('Y-m-d H:i'), ['size' => 9, 'color' => '777777']);

        $sluggedName = (string) str($eventTemplate->name ?? 'oferta')->slug();
        $sluggedPlace = (string) str($startPlaceName ?? 'region')->slug();
        $fileName = $sluggedName . '-' . $sluggedPlace . '-' . now()->format('Ymd-His') . '.docx';

        $tempDir = storage_path('app/tmp');
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }

        $filePath = $tempDir . DIRECTORY_SEPARATOR . $fileName;

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($filePath);

        return response()->download(
            $filePath,
            $fileName,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
        )->deleteFileAfterSend(true);
    }

    /**
     * Resolve minimal PLN price from a collection of EventTemplatePricePerPerson models.
     * Rules:
     *  - If $startPlaceId provided: consider only prices with that start_place_id and price_per_person > 0
     *  - Else: consider all prices with price_per_person > 0
     *  - Optionally filter prices by qty range (provide $qtyMin and $qtyMax)
     *  - Keep only prices in PLN (based on currency.code == 'PLN' or name contains 'ZŁOT')
     *  - Group by event_template_qty_id and for each group pick the latest record (max id)
     *  - Return the minimal price_per_person among those latest-per-qty records, or null if none
     *
     * @param Collection $prices
     * @param int|null $startPlaceId
     * @param int|null $qtyMin  inclusive minimal qty to consider (optional)
     * @param int|null $qtyMax  inclusive maximal qty to consider (optional)
     * @return float|null
     */
    private function resolveMinPlnFromPrices(Collection $prices, $startPlaceId = null, ?int $qtyMin = null, ?int $qtyMax = null): ?float
    {
        // detect PLN by currency relation (schema may not have code; rely on symbol/name)
        $isPln = function ($cur) {
            if (!$cur) return false;
            $code = strtoupper(trim($cur->code ?? ''));
            $symbol = strtoupper(trim($cur->symbol ?? ''));
            $name = strtoupper(trim($cur->name ?? ''));
            return $code === 'PLN' || $symbol === 'PLN' || str_contains($name, 'ZŁOT');
        };

        if ($startPlaceId) {
            $candidate = $prices->filter(fn($p) => $p->price_per_person > 0 && (int)$p->start_place_id === (int)$startPlaceId);
        } else {
            $candidate = $prices->filter(fn($p) => $p->price_per_person > 0);
        }

        // If qty range provided, prefer prices whose qty variant fits into that range
        if (!is_null($qtyMin) || !is_null($qtyMax)) {
            $candidate = $candidate->filter(function ($p) use ($qtyMin, $qtyMax) {
                if (!isset($p->eventTemplateQty) || !isset($p->eventTemplateQty->qty)) return false;
                $qty = (int)$p->eventTemplateQty->qty;
                if (!is_null($qtyMin) && $qty < $qtyMin) return false;
                if (!is_null($qtyMax) && $qty > $qtyMax) return false;
                return true;
            })->values();
        }

        $latestPlnPerQty = $candidate
            ->filter(fn($p) => isset($p->currency) && $isPln($p->currency))
            ->groupBy('event_template_qty_id')
            ->map(fn($g) => $g->sortByDesc('id')->first())
            ->values();

        if ($latestPlnPerQty->count() === 0) return null;
        return $latestPlnPerQty->min('price_per_person');
    }

    private function buildPriceRangesForPlace(Collection $prices, ?int $startPlaceId): array
    {
        $filterByPlace = function ($price) use ($startPlaceId) {
            if ($startPlaceId) {
                return (int)($price->start_place_id ?? 0) === (int)$startPlaceId;
            }

            return $price->start_place_id === null;
        };

        $validForPlace = $prices
            ->filter(fn($p) => $p->price_per_person > 0)
            ->filter($filterByPlace); // już kolekcja tylko dla wskazanego miejsca

        if ($validForPlace->isEmpty()) {
            return [];
        }

        $isPln = function ($price) {
            $currency = $price->currency ?? null;
            if (!$currency) {
                return false;
            }

            $code = strtoupper(trim($currency->code ?? ''));
            $symbol = strtoupper(trim($currency->symbol ?? ''));
            $name = strtoupper(trim($currency->name ?? ''));

            return $code === 'PLN' || $symbol === 'PLN' || str_contains($name, 'ZŁOT');
        };

        $plnPrices = $validForPlace->filter($isPln);

        if ($plnPrices->isEmpty()) {
            return [];
        }

        $latestPlnPerQty = $plnPrices
            ->groupBy('event_template_qty_id')
            ->map(fn($group) => $group->sortByDesc('id')->first())
            ->filter(fn($price) => optional($price->eventTemplateQty)->qty)
            ->sortBy(fn($price) => (int)optional($price->eventTemplateQty)->qty)
            ->values();

        if ($latestPlnPerQty->isEmpty()) {
            return [];
        }

        $qtyKeys = $latestPlnPerQty
            ->map(fn($price) => (int)optional($price->eventTemplateQty)->qty)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $ranges = [];
        $allForPlace = $validForPlace; // zawiera wszystkie waluty dla tego miejsca

        foreach ($qtyKeys as $index => $qty) {
            $price = $latestPlnPerQty->first(function ($candidate) use ($qty) {
                return (int)optional($candidate->eventTemplateQty)->qty === (int)$qty;
            });

            if (!$price) {
                continue;
            }

            $nextQty = $qtyKeys[$index + 1] ?? null;
            $rangeEnd = $nextQty ? max($qty, (int)$nextQty - 1) : 55;

            $otherLabels = [];
            $qtyId = optional($price->eventTemplateQty)->id;
            if ($qtyId) {
                $others = $allForPlace
                    ->where('event_template_qty_id', $qtyId)
                    ->filter(fn($candidate) => !$isPln($candidate));

                if ($others->isNotEmpty()) {
                    $grouped = $others->groupBy(function ($candidate) {
                        $currency = $candidate->currency ?? null;
                        if (!$currency) {
                            return 'OTHER';
                        }

                        $symbol = strtoupper(trim($currency->symbol ?? ''));
                        $code = strtoupper(trim($currency->code ?? ''));

                        return $symbol ?: ($code ?: 'OTHER');
                    });

                    $orderedKeys = $grouped->keys()->sort(function ($a, $b) {
                        $a = (string)$a;
                        $b = (string)$b;
                        if ($a === $b) {
                            return 0;
                        }
                        if ($a === 'EUR') {
                            return -1;
                        }
                        if ($b === 'EUR') {
                            return 1;
                        }
                        return strcmp($a, $b);
                    });

                    foreach ($orderedKeys as $currencyKey) {
                        $group = $grouped->get($currencyKey);
                        if (!$group) {
                            continue;
                        }

                        $min = $group->min('price_per_person');
                        if ($min && $min > 0) {
                            $amount = (int)ceil($min);
                            $otherLabels[] = "+ {$amount} {$currencyKey}";
                        }
                    }
                }
            }

            $ranges[] = [
                'from' => (int)$qty,
                'to' => (int)$rangeEnd,
                'price' => (int)ceil(((float)$price->price_per_person) / 5) * 5,
                'other' => $otherLabels,
            ];
        }

        return array_reverse($ranges);
    }

    private function extractProgramForWord(EventTemplate $eventTemplate): array
    {
        $program = [];
        $duration = (int)($eventTemplate->duration_days ?? 0);
        $points = $eventTemplate->programPoints ?? collect();

        $filterPoint = function ($point) {
            $pivot = $point->pivot ?? null;
            if (!$pivot) {
                return false;
            }

            $include = $pivot->include_in_program ?? false;
            $active = $pivot->active ?? true;

            return (bool)$include && (bool)$active;
        };

        for ($day = 1; $day <= max($duration, 0); $day++) {
            $dayPoints = $points
                ->filter(fn($point) => (int)($point->pivot->day ?? 0) === $day)
                ->filter($filterPoint)
                ->sortBy(fn($point) => $point->pivot->order ?? $point->pivot->order_number ?? 0)
                ->map(function ($point) use ($eventTemplate) {
                    $title = preg_replace('/\s*-?\s*\d+:\d+h?.*$/', '', strip_tags($point->name ?? ''));
                    $pivot = $point->pivot ?? null;
                    $bold = (bool)($pivot->show_title_style ?? true);
                    $descriptionAllowed = (bool)($pivot->show_description ?? true);
                    $description = $descriptionAllowed ? $this->sanitizeWordText(strip_tags($point->description ?? '')) : '';
                    $descriptionHtml = $descriptionAllowed ? $this->sanitizeHtmlPreservingInline($point->description ?? '') : '';
                    $title = $this->sanitizeWordText($title);

                    $children = collect($point->children ?? [])
                        ->filter(function ($child) use ($eventTemplate) {
                            $prop = $child->childPropertiesForTemplate($eventTemplate->id)->first();
                            if (!$prop) {
                                return false;
                            }

                            $pivot = $prop->pivot ?? null;
                            if (!$pivot) {
                                return false;
                            }

                            return (bool)($pivot->include_in_program ?? false) && (bool)($pivot->active ?? true);
                        })
                        ->sortBy(function ($child) use ($eventTemplate) {
                            $prop = $child->childPropertiesForTemplate($eventTemplate->id)->first();
                            return $prop->pivot->order ?? $prop->pivot->order_number ?? 0;
                        })
                        ->map(function ($child) use ($eventTemplate) {
                            $prop = $child->childPropertiesForTemplate($eventTemplate->id)->first();
                            $title = preg_replace('/\s*-?\s*\d+:\d+h?.*$/', '', strip_tags($child->name ?? ''));
                            $bold = (bool)($prop->pivot->show_title_style ?? true);
                            $descriptionAllowed = (bool)($prop->pivot->show_description ?? true);
                            $description = $descriptionAllowed ? $this->sanitizeWordText(strip_tags($child->description ?? '')) : '';
                            $descriptionHtml = $descriptionAllowed ? $this->sanitizeHtmlPreservingInline($child->description ?? '') : '';
                            $title = $this->sanitizeWordText($title);

                            return [
                                'title' => $title,
                                'bold' => $bold,
                                'description' => $description,
                            ];
                        })
                        ->values()
                        ->all();

                    return [
                        'title' => $title,
                        'bold' => $bold,
                        'description' => $description,
                        'children' => $children,
                    ];
                })
                ->values()
                ->all();

            if (!empty($dayPoints)) {
                $program[] = [
                    'label' => "Dzień {$day}",
                    'points' => $dayPoints,
                ];
            }
        }

        // Fakultatywny dzień (duration + 1)
        $facultativeDay = $duration + 1;
        $facultativePoints = $points
            ->filter(fn($point) => (int)($point->pivot->day ?? 0) === $facultativeDay)
            ->filter($filterPoint)
            ->sortBy(fn($point) => $point->pivot->order ?? $point->pivot->order_number ?? 0)
            ->map(function ($point) {
                $title = preg_replace('/\s*-?\s*\d+:\d+h?.*$/', '', strip_tags($point->name ?? ''));
                $pivot = $point->pivot ?? null;
                $bold = (bool)($pivot->show_title_style ?? true);
                $descriptionAllowed = (bool)($pivot->show_description ?? true);
                $description = $descriptionAllowed ? $this->sanitizeWordText(strip_tags($point->description ?? '')) : '';
                $title = $this->sanitizeWordText($title);

                return [
                    'title' => $title,
                    'bold' => $bold,
                    'description' => $description,
                    'children' => [],
                ];
            })
            ->values()
            ->all();

        if (!empty($facultativePoints)) {
            $program[] = [
                'label' => 'Fakultatywnie proponujemy',
                'points' => $facultativePoints,
            ];
        }

        return $program;
    }

    private function configureWordSectionBranding(Section $section): void
    {
        $header = $section->addHeader();
        // Try to add logo image from public/uploads/logo.png. If unavailable or adding fails,
        // fall back to the original textual header.
        $logoPath = public_path('uploads/logo.png');
        if ($logoPath && file_exists($logoPath)) {
            try {
                // Add image centered in a TextRun. Set only width to preserve aspect ratio
                // and make it ~50% smaller (previously ~180 width -> now 90).
                $textRun = $header->addTextRun(['alignment' => 'center']);
                $textRun->addImage($logoPath, ['width' => 90]);
                // Add larger spacing below the logo so header doesn't touch body text.
                // Increase vertical gap (3 line breaks) per user request.
                $header->addTextBreak(3);
            } catch (\Throwable $e) {
                // fallback to text header when image cannot be inserted
                $header->addText(
                    'Biuro Podróży RAFA',
                    ['bold' => true, 'size' => 12, 'color' => '0070C0'],
                    ['alignment' => 'center']
                );
            }
        } else {
            $header->addText(
                'Biuro Podróży RAFA',
                ['bold' => true, 'size' => 12, 'color' => '0070C0'],
                ['alignment' => 'center']
            );
        }

        $footer = $section->addFooter();
        $footer->addText(
            'Biuro Podróży RAFA • Ul. Marii Konopnickiej 6 • 00-491 Warszawa',
            ['size' => 9, 'color' => '666666'],
            ['alignment' => 'center']
        );
        $footer->addText(
            'tel. +48 606 102 243 • rafa@bprafa.pl • www.bprafa.pl • NIP 716-250-87-61',
            ['size' => 9, 'color' => '666666'],
            ['alignment' => 'center']
        );
        $footer->addPreserveText(
            'Strona {PAGE} z {NUMPAGES}',
            ['size' => 9, 'color' => '666666'],
            ['alignment' => 'right']
        );
    }

    private function appendHtmlSnippetToSection(Section $section, string $html): void
    {
        foreach ($this->convertHtmlSnippetToLines($html) as $line) {
            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, '-')) {
                $content = ltrim(substr($line, 1));
                if ($content === '') {
                    continue;
                }
                $section->addListItem($this->sanitizeWordText($content));
            } else {
                $section->addText($this->sanitizeWordText($line));
            }
        }
    }

    private function convertHtmlSnippetToLines(string $html): array
    {
        $working = $html;
        $working = preg_replace('/<li[^>]*>/i', "\n- ", $working) ?? $working;
        $working = preg_replace('/<\/li>/i', "\n", $working) ?? $working;
        $working = preg_replace('/<p[^>]*>/i', "\n", $working) ?? $working;
        $working = str_ireplace(['</p>'], "\n", $working);
        $working = preg_replace('/<br\s*\/?/i', "\n", $working) ?? $working;
        $working = strip_tags($working);
        $working = html_entity_decode($working, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $lines = preg_split('/\r\n|\r|\n/', $working) ?: [];
        $result = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed !== '') {
                $result[] = $trimmed;
            }
        }

        return $result;
    }

    private function sanitizeWordText(?string $text): string
    {
        if ($text === null) {
            return '';
        }

        $text = (string)$text;
        if ($text === '') {
            return '';
        }

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;
        return trim($text);
    }

    /**
     * Keep only simple inline HTML tags (<b>, <strong>, <i>, <em>, <u>) and return cleaned HTML
     * that will be safe to parse and convert to text runs.
     */
    private function sanitizeHtmlPreservingInline(?string $html): string
    {
        if (empty($html)) {
            return '';
        }
        // Allow only simple inline tags
        $allowed = '<b><strong><i><em><u>';
        $clean = strip_tags($html, $allowed);
        // Normalize tags: map <strong> -> <b>, <em> -> <i>
        $clean = str_ireplace(['<strong>', '</strong>', '<em>', '</em>'], ['<b>', '</b>', '<i>', '</i>'], $clean);
        return trim($clean);
    }

    /**
     * Append a small HTML fragment (with only <b>, <i>, <u>) into a TextRun or ListItemRun container
     * preserving inline styles by calling addText for each fragment.
     *
     * @param \PhpOffice\PhpWord\Element\TextRun|\PhpOffice\PhpWord\Element\ListItemRun $container
     * @param string $html
     * @return void
     */
    private function appendInlineHtmlToContainer($container, string $html): void
    {
        if (trim($html) === '') return;

        // Break input into tags and text
        $pattern = '/(<b>|<\/b>|<i>|<\/i>|<u>|<\/u>)/i';
        $parts = preg_split($pattern, $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $currentStyle = [];
        foreach ($parts as $part) {
            $lower = strtolower($part);
            if ($lower === '<b>') {
                $currentStyle['bold'] = true;
                continue;
            }
            if ($lower === '</b>') {
                unset($currentStyle['bold']);
                continue;
            }
            if ($lower === '<i>') {
                $currentStyle['italic'] = true;
                continue;
            }
            if ($lower === '</i>') {
                unset($currentStyle['italic']);
                continue;
            }
            if ($lower === '<u>') {
                $currentStyle['underline'] = 'single';
                continue;
            }
            if ($lower === '</u>') {
                unset($currentStyle['underline']);
                continue;
            }

            // plain text chunk
            $text = html_entity_decode(trim($part), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($text === '') continue;
            // addText accepts style array; map underline bool to PhpWord value
            $style = [];
            if (!empty($currentStyle['bold'])) $style['bold'] = true;
            if (!empty($currentStyle['italic'])) $style['italic'] = true;
            if (!empty($currentStyle['underline'])) $style['underline'] = $currentStyle['underline'];

            // PhpWord containers support addText
            try {
                $container->addText($text, $style);
            } catch (\Throwable $e) {
                // best-effort: if container doesn't support addText, skip
            }
        }
    }

    private function verifyTurnstileToken(Request $request, string $context, bool $requireToken = true): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        $secretKey = config('services.turnstile.secret_key');
        $siteKey = config('services.turnstile.site_key');
        if (!$secretKey || !$siteKey) {
            return true;
        }

        $token = $request->input('cf-turnstile-response');
        if (empty($token)) {
            if (!$requireToken) {
                return true;
            }
            Log::notice('Turnstile token missing', [
                'context' => $context,
                'ip' => $request->ip(),
                'route' => optional($request->route())->getName(),
            ]);
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => $secretKey,
                    'response' => $token,
                    'remoteip' => $request->ip(),
                    'sitekey' => $siteKey,
                ]);

            if (!$response->ok()) {
                Log::warning('Turnstile verification HTTP failure', [
                    'context' => $context,
                    'status' => $response->status(),
                ]);
                return false;
            }

            $payload = $response->json();
            $success = (bool)($payload['success'] ?? false);

            $reportedAction = $payload['action'] ?? null;
            if ($reportedAction !== null && $reportedAction !== $context) {
                Log::info('Turnstile verification action mismatch', [
                    'context' => $context,
                    'reported_action' => $reportedAction,
                ]);
                return false;
            }

            if (!$success) {
                Log::info('Turnstile verification denied', [
                    'context' => $context,
                    'errors' => $payload['error-codes'] ?? [],
                ]);
            }

            return $success;
        } catch (\Throwable $e) {
            Log::warning('Turnstile verification exception', [
                'context' => $context,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function insurance()
    {
        return view('front.insurance');
    }

    public function documents()
    {
        $sections = \App\Models\DocumentSection::with(['documents.attachments'])->orderBy('order_number')->get();
        return view('front.documents', compact('sections'));
    }

    public function document($slug)
    {
        $document = \App\Models\Document::where('slug', $slug)->where('is_published', true)->firstOrFail();
        return view('front.document', compact('document'));
    }

    public function contact()
    {
        return view('front.contact');
    }

    public function faq()
    {
        return view('front.faq');
    }

    public function sendEmail(Request $request)
    {
        // Rate-limiting (basic) via cache-based token to complement route throttle
        $ip = $request->ip();
        $ua = substr((string) $request->header('User-Agent'), 0, 191);
        $cacheKey = 'sendEmail:' . sha1($ip . '|' . $ua . '|' . ($request->input('email') ?? ''));
        if (cache()->has($cacheKey)) {
            return back()->with('success', 'Dziękujemy! Jeśli przed chwilą już wysłałeś zapytanie, poczekaj chwilę przed kolejną wiadomością.');
        }

        // Basic validation
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:200'],
            'email' => ['required', 'email:rfc,dns', 'max:200'],
            'telephone' => ['required', 'string', 'max:50'],
            'message' => ['nullable', 'string', 'max:4000'],
            // hidden fields
            'event_name' => ['nullable', 'string', 'max:255'],
            'event_url' => ['nullable', 'url', 'max:2048'],
            'start_place_name' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:0'], // honeypot must remain empty
            'form_ts' => ['nullable', 'string', 'max:32'],
            'cf-turnstile-response' => ['nullable', 'string', 'max:2048'],
        ], [
            'email.required' => 'Podaj poprawny adres email.',
            'email.email' => 'Podaj poprawny adres email.',
            'telephone.required' => 'Podaj numer telefonu.',
        ]);

        // Honeypot: if filled, silently accept but do nothing
        if (!empty($data['website'] ?? '')) {
            return back()->with('success', 'Dziękujemy za wiadomość! Skontaktujemy się wkrótce.');
        }
        // Minimal fill time: at least 3s between load and submit
        $okTime = true;
        if (!empty($data['form_ts'])) {
            $delta = (int) (microtime(true) * 1000) - (int) $data['form_ts'];
            if ($delta < 3000) {
                $okTime = false;
            }
        }
        if (!$okTime) {
            return back()->with('success', 'Dziękujemy za wiadomość! Skontaktujemy się wkrótce.');
        }

        if (!$this->verifyTurnstileToken($request, 'contact_form')) {
            return back()->with('success', 'Dziękujemy za wiadomość! Skontaktujemy się wkrótce.');
        }

        // Basic referer/domain check to reduce cross-origin spam posts (aktywne tylko w produkcji)
        if (app()->environment('production')) {
            $ref = (string) $request->headers->get('referer', '');
            if (!empty($ref)) {
                try {
                    $host = parse_url($ref, PHP_URL_HOST);
                    $appHost = parse_url(config('app.url') ?: url('/'), PHP_URL_HOST);
                    if ($host && $appHost && $host !== $appHost) {
                        return back()->with('success', 'Dziękujemy za wiadomość! Skontaktujemy się wkrótce.');
                    }
                } catch (\Throwable $e) {
                    // ignore parsing errors
                }
            }
        }

        $eventName = $data['event_name'] ?? 'Oferta';
        $startPlace = $data['start_place_name'] ?? '';
        // Temat: "Zapytanie ze strony - {nazwa wycieczki} {miejsce wyjazdu}"
        $subject = "Zapytanie ze strony - {$eventName}" . (strlen(trim($startPlace)) ? " {$startPlace}" : "");
        $eventUrl = $data['event_url'] ?? url('/');

        // Build body
        $lines = [];
        $lines[] = "Nowe zapytanie z formularza na stronie oferty.";
        $lines[] = "Oferta: {$eventName}";
        $lines[] = "Miejsce wyjazdu: {$startPlace}";
        $lines[] = "Link: {$eventUrl}";
        $lines[] = "";
        $lines[] = "Dane kontaktowe:";
        $lines[] = "Imię i nazwisko: " . ($data['name'] ?? '');
        $lines[] = "Email: " . ($data['email'] ?? '');
        $lines[] = "Telefon: " . ($data['telephone'] ?? '');
        $lines[] = "";
        $lines[] = "Wiadomość:";
        $lines[] = trim((string)($data['message'] ?? ''));
        // Trim overly long lines to avoid header injection-like payloads and huge lines
        $lines = array_map(function ($l) {
            return mb_substr($l, 0, 500);
        }, $lines);
        $body = implode("\n", $lines);

        // Recipients: test vs production
        $configured = config('mail.inquiries_to');
        if (is_string($configured) && strlen(trim($configured)) > 0) {
            $toList = [trim($configured)];
        } elseif (is_array($configured) && !empty($configured)) {
            $toList = $configured;
        } else {
            // Domyślni odbiorcy, jeśli brak konfiguracji: kieruj na skrzynkę firmową
            $toList = ['rafa@bprafa.pl'];
        }
        // Nie dołączaj automatycznie prywatnego adresu; respektuj wyłącznie konfigurację
        $toList = array_values(array_unique(array_filter($toList)));
        try {
            Mail::raw($body, function ($m) use ($toList, $subject, $data) {
                $m->to($toList)
                    ->subject($subject);
                if (!empty($data['email'])) {
                    // Wyślij kopię do nadawcy i ustaw reply-to
                    $m->cc($data['email'], $data['name'] ?? null);
                    $m->replyTo($data['email'], $data['name'] ?? null);
                }
            });
        } catch (\Throwable $e) {
            try {
                Log::error('sendEmail error: ' . $e->getMessage());
            } catch (\Throwable $ee) {
            }
            // Do not reveal failures to bots; generic success
        }

        // Simple throttle: block duplicates for 120s (per IP + UA + email)
        cache()->put($cacheKey, 1, now()->addSeconds(120));

        return back()->with('success', 'Dziękujemy! Twoja wiadomość została wysłana.');
    }
}
