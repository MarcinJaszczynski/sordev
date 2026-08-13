<?php

namespace App\Models;

use App\Models\Concerns\HasStickyNotes;
use App\Models\Concerns\HasTasks;
use App\Support\Region;
use App\Support\StoragePath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Model EventTemplate
 * Reprezentuje szablon wydarzenia w systemie.
 *
 * @property int $id
 * @property string $name
 * @property string|null $subtitle
 * @property string $slug
 * @property int $duration_days
 * @property bool $is_active
 * @property string|null $featured_image
 * @property string|null $event_description
 * @property array|null $gallery
 * @property string|null $office_description
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class EventTemplate extends Model
{
    use HasFactory, HasStickyNotes, HasTasks, SoftDeletes;

    /**
     * Automatyczne zapewnienie unikalności slugów przy tworzeniu/aktualizacji.
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            // Jeśli slug nie jest ustawiony, generuj z nazwy
            $baseSlug = $model->slug ?: Str::slug($model->name);
            $slug = $baseSlug;
            $i = 1;

            // Sprawdzaj unikalność (ignoruj aktualny rekord przy edycji)
            while (static::where('slug', $slug)
                ->when($model->exists, fn ($q) => $q->where('id', '!=', $model->id))
                ->exists()
            ) {
                $slug = $baseSlug.'-'.$i;
                $i++;
            }
            $model->slug = $slug;
        });
    }

    /**
     * Casty atrybutów.
     */
    protected $casts = [
        'gallery' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Mutator: zawsze zapisuj featured_image jako string (pierwszy element tablicy lub null)
     */
    public function setFeaturedImageAttribute($value)
    {
        if (is_array($value)) {
            $this->attributes['featured_image'] = $value[0] ?? null;
        } else {
            $this->attributes['featured_image'] = $value;
        }
    }

    /**
     * Accessor: zwracaj pełną ścieżkę względną względem dysku dla featured_image,
     * jeśli w bazie zapisany jest sam basename.
     */
    public function getFeaturedImageAttribute($value)
    {
        if (empty($value)) {
            return $value;
        }

        // Jeśli już jest ścieżka z katalogiem, zostaw bez zmian
        if (is_string($value) && str_contains($value, '/')) {
            return $value;
        }

        // W przeciwnym razie dołóż domyślny katalog dla miniatur
        return 'event-templates/'.ltrim((string) $value, '/');
    }

    /**
     * Accessor: zwracaj tablicę ścieżek dla galerii i dopilnuj, by elementy
     * miały prefiks katalogu, jeśli w bazie zapisane są same nazwy plików.
     */
    public function getGalleryAttribute($value)
    {
        // Upewnij się, że mamy tablicę
        $items = $value;
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $items = is_array($decoded) ? $decoded : [];
        } elseif (! is_array($value)) {
            $items = [];
        }

        return array_map(function ($path) {
            if (empty($path)) {
                return $path;
            }
            if (is_string($path) && str_contains($path, '/')) {
                return $path;
            }

            return 'event-templates/gallery/'.ltrim((string) $path, '/');
        }, $items);
    }

    public function getFullImagePathAttribute(): ?string
    {
        return StoragePath::normalize($this->featured_image);
    }

    public function getPreviewImagePathAttribute(): ?string
    {
        $fullPath = $this->full_image_path;

        if (! $fullPath) {
            return null;
        }

        if (str_contains($fullPath, '/thumbs/')) {
            return $fullPath;
        }

        $directory = pathinfo($fullPath, PATHINFO_DIRNAME);
        $filename = pathinfo($fullPath, PATHINFO_BASENAME);

        if (! $directory || $directory === '.' || $directory === '/') {
            return 'thumbs/'.$filename;
        }

        return trim($directory, '/').'/thumbs/'.$filename;
    }

    public function getFullImageUrlAttribute(): ?string
    {
        return $this->publicStorageUrlIfExists($this->full_image_path)
            ?: $this->resolveImageUrlFromFeaturedName(false)
            ?: $this->resolveImageUrlFromSlug(false);
    }

    public function getPreviewImageUrlAttribute(): ?string
    {
        return $this->publicStorageUrlIfExists($this->preview_image_path)
            ?: $this->resolveImageUrlFromFeaturedName(true)
            ?: $this->resolveImageUrlFromSlug(true)
            ?: $this->full_image_url;
    }

    private function publicStorageUrlIfExists(?string $path): ?string
    {
        $normalized = StoragePath::normalize($path);

        if (! $normalized) {
            return null;
        }

        return Storage::disk('public')->exists($normalized)
            ? StoragePath::publicUrl($normalized)
            : null;
    }

    private function resolveImageUrlFromSlug(bool $preferThumb): ?string
    {
        $slug = Str::slug((string) ($this->slug ?: $this->name));

        if ($slug === '') {
            return null;
        }

        return $this->resolveImageUrlFromCandidates([$slug], $preferThumb, true);
    }

    private function resolveImageUrlFromFeaturedName(bool $preferThumb): ?string
    {
        $normalized = StoragePath::normalize($this->featured_image);

        if (! $normalized) {
            return null;
        }

        $filenameSlug = Str::slug((string) pathinfo($normalized, PATHINFO_FILENAME));

        if ($filenameSlug === '') {
            return null;
        }

        return $this->resolveImageUrlFromCandidates([$filenameSlug], $preferThumb, true);
    }

    private function resolveImageUrlFromCandidates(array $candidateSlugs, bool $preferThumb, bool $allowTokenMatch): ?string
    {
        $directories = $this->imageSearchDirectories($preferThumb);

        foreach ($candidateSlugs as $candidateSlug) {
            if (! is_string($candidateSlug) || $candidateSlug === '') {
                continue;
            }

            foreach ($directories as $directory) {
                $index = $this->directorySlugIndex($directory);

                if (isset($index[$candidateSlug])) {
                    return StoragePath::publicUrl($index[$candidateSlug]);
                }
            }
        }

        if (! $allowTokenMatch) {
            return null;
        }

        foreach ($candidateSlugs as $candidateSlug) {
            if (! is_string($candidateSlug) || $candidateSlug === '') {
                continue;
            }

            $matchedPath = $this->resolveTokenMatchedPath($candidateSlug, $directories);

            if ($matchedPath) {
                return StoragePath::publicUrl($matchedPath);
            }
        }

        foreach ($candidateSlugs as $candidateSlug) {
            if (! is_string($candidateSlug) || $candidateSlug === '') {
                continue;
            }

            $matchedPath = $this->resolveLooseOverlapPath($candidateSlug, $directories);

            if ($matchedPath) {
                return StoragePath::publicUrl($matchedPath);
            }
        }

        return null;
    }

    private function imageSearchDirectories(bool $preferThumb): array
    {
        return $preferThumb
            ? ['event-templates/thumbs', 'event-templates/gallery/thumbs', 'event-templates', 'event-templates/gallery']
            : ['event-templates', 'event-templates/gallery', 'event-templates/thumbs', 'event-templates/gallery/thumbs'];
    }

    private function directorySlugIndex(string $directory): array
    {
        static $directorySlugIndex = [];

        if (! array_key_exists($directory, $directorySlugIndex)) {
            $directorySlugIndex[$directory] = [];

            foreach (Storage::disk('public')->files($directory) as $filePath) {
                $name = pathinfo($filePath, PATHINFO_FILENAME);
                $fileSlug = Str::slug($name);

                if ($fileSlug !== '' && ! isset($directorySlugIndex[$directory][$fileSlug])) {
                    $directorySlugIndex[$directory][$fileSlug] = $filePath;
                }
            }
        }

        return $directorySlugIndex[$directory];
    }

    private function resolveTokenMatchedPath(string $candidateSlug, array $directories): ?string
    {
        $candidateTokens = $this->slugTokens($candidateSlug);
        $candidateAlphaTokens = array_values(array_filter($candidateTokens, static fn (string $token): bool => ! ctype_digit($token)));

        if ($candidateAlphaTokens === []) {
            return null;
        }

        $bestPath = null;
        $bestDistance = PHP_INT_MAX;
        $bestTokenCount = PHP_INT_MAX;

        foreach ($directories as $directory) {
            foreach ($this->directorySlugIndex($directory) as $fileSlug => $filePath) {
                $fileTokens = $this->slugTokens($fileSlug);
                $fileAlphaTokens = array_values(array_filter($fileTokens, static fn (string $token): bool => ! ctype_digit($token)));

                if ($fileAlphaTokens === []) {
                    continue;
                }

                if (array_diff($candidateAlphaTokens, $fileAlphaTokens) !== []) {
                    continue;
                }

                $distance = levenshtein($candidateSlug, $fileSlug);
                $tokenCount = count($fileTokens);

                if ($distance < $bestDistance || ($distance === $bestDistance && $tokenCount < $bestTokenCount)) {
                    $bestDistance = $distance;
                    $bestTokenCount = $tokenCount;
                    $bestPath = $filePath;

                    continue;
                }
            }
        }

        return $bestPath;
    }

    private function resolveLooseOverlapPath(string $candidateSlug, array $directories): ?string
    {
        $candidateTokens = array_values(array_filter(
            $this->slugTokens($candidateSlug),
            static fn (string $token): bool => ! ctype_digit($token) && strlen($token) >= 4
        ));

        if ($candidateTokens === []) {
            return null;
        }

        $bestPath = null;
        $bestOverlap = 0;
        $bestRatio = 0.0;
        $bestDistance = PHP_INT_MAX;
        $bestTokenCount = PHP_INT_MAX;

        foreach ($directories as $directory) {
            foreach ($this->directorySlugIndex($directory) as $fileSlug => $filePath) {
                $fileTokens = array_values(array_filter(
                    $this->slugTokens($fileSlug),
                    static fn (string $token): bool => ! ctype_digit($token) && strlen($token) >= 4
                ));

                if ($fileTokens === []) {
                    continue;
                }

                $overlap = count(array_intersect($candidateTokens, $fileTokens));

                if ($overlap === 0) {
                    continue;
                }

                $ratio = $overlap / count($candidateTokens);
                $distance = levenshtein($candidateSlug, $fileSlug);
                $tokenCount = count($fileTokens);

                if (
                    $overlap > $bestOverlap
                    || ($overlap === $bestOverlap && $ratio > $bestRatio)
                    || ($overlap === $bestOverlap && $ratio === $bestRatio && $distance < $bestDistance)
                    || ($overlap === $bestOverlap && $ratio === $bestRatio && $distance === $bestDistance && $tokenCount < $bestTokenCount)
                ) {
                    $bestPath = $filePath;
                    $bestOverlap = $overlap;
                    $bestRatio = $ratio;
                    $bestDistance = $distance;
                    $bestTokenCount = $tokenCount;
                }
            }
        }

        return $bestPath;
    }

    private function slugTokens(string $slug): array
    {
        return array_values(array_filter(
            explode('-', Str::slug($slug)),
            static fn (string $token): bool => $token !== ''
        ));
    }

    /**
     * Relacja wiele-do-wielu z tagami
     */
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'event_template_tag');
    }

    public function eventTypes()
    {
        return $this->belongsToMany(EventType::class, 'event_template_event_type', 'event_template_id', 'event_type_id');
    }

    public function transportTypes()
    {
        return $this->belongsToMany(TransportType::class);
    }

    /**
     * Szablon ma dokładnie wskazane rodzaje transportu — bez dodatkowych.
     *
     * @param  array<int, int|string>  $transportTypeIds
     */
    public function scopeWithExactTransportTypes(Builder $query, array $transportTypeIds): Builder
    {
        $transportTypeIds = array_values(array_unique(array_filter(array_map('intval', $transportTypeIds))));

        if ($transportTypeIds === []) {
            return $query;
        }

        foreach ($transportTypeIds as $transportTypeId) {
            $query->whereHas('transportTypes', function (Builder $relationQuery) use ($transportTypeId): void {
                $relationQuery->where('transport_types.id', $transportTypeId);
            });
        }

        return $query->whereDoesntHave('transportTypes', function (Builder $relationQuery) use ($transportTypeIds): void {
            $relationQuery->whereNotIn('transport_types.id', $transportTypeIds);
        });
    }

    public function startPlace()
    {
        return $this->belongsTo(Place::class, 'start_place_id');
    }

    public function endPlace()
    {
        return $this->belongsTo(Place::class, 'end_place_id');
    }

    // Relacja: jeden event_template może mieć jeden event_price_description (pivot, nullable)
    public function eventPriceDescription()
    {
        return $this->belongsToMany(
            \App\Models\EventPriceDescription::class,
            'event_template_event_price_description',
            'event_template_id',
            'event_price_description_id'
        );
    }

    /**
     * Pola masowo przypisywalne
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'subtitle',
        'slug',
        'duration_days',
        'set_default_child_count',
        'set_default_slot_minutes',
        'is_active',
        'featured_image',
        'event_description',
        'gallery',
        'office_description',
        'notes',
        'transfer_km',
        'program_km',
        'bus_id',
        'transport_notes',
        'markup_id', // dodajemy pole do przypisania narzutu
        'start_place_id',
        'end_place_id',
        'show_title_style',
        'show_description',
        'name',
        'subtitle',
        'slug',
        'duration_days',
        'set_default_child_count',
        'set_default_slot_minutes',
        'is_active',
        'featured_image',
        'event_description',
        'gallery',
        'office_description',
        'notes',
        'transfer_km',
        'program_km',
        'bus_id',
        'markup_id',
        'start_place_id',
        'end_place_id',
        'transport_notes',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'seo_canonical',
        'seo_og_title',
        'seo_og_description',
        'seo_og_image',
        // usunięte: 'seo_twitter_title', 'seo_twitter_description', 'seo_twitter_image', 'seo_schema', 'transfer_km2', 'program_km2'
        // usunięte: 'transfer_km2', 'program_km2'
        // usunięte: 'seo_twitter_title', 'seo_twitter_description', 'seo_twitter_image', 'seo_schema'
    ];

    /**
     * Relacja wiele-do-wielu z punktami programu (tymczasowa implementacja)
     */
    public function programPoints()
    {
        return $this->belongsToMany(\App\Models\EventTemplateProgramPoint::class, 'event_template_event_template_program_point')
            ->withPivot([
                'id',
                'day',
                'order',
                'notes',
                'start_time',
                'end_time',
                'include_in_program',
                'include_in_calculation',
                'active',
                'show_title_style',
                'show_description',
            ]);
    }

    /**
     * Relacja wiele-do-wielu z podpunktami programu (pivot z właściwościami)
     */
    public function programPointChildren()
    {
        return $this->belongsToMany(
            \App\Models\EventTemplateProgramPoint::class,
            'event_template_program_point_child_pivot',
            'event_template_id',
            'program_point_child_id'
        )
            ->withPivot([
                'id',
                'include_in_program',
                'include_in_calculation',
                'active',
                'created_at',
                'updated_at',
            ]);
    }

    /**
     * Warianty ilości powiązane z szablonem przez cennik (event_template_price_per_person).
     */
    public function qtyVariants(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            EventTemplateQty::class,
            'event_template_price_per_person',
            'event_template_id',
            'event_template_qty_id',
        )->distinct();
    }

    /**
     * Relacja jeden-do-wielu z cenami za osobę
     */
    public function pricesPerPerson()
    {
        return $this->hasMany(EventTemplatePricePerPerson::class);
    }

    /**
     * Ubezpieczenie przypisane do każdego dnia (event_template_day_insurance)
     */
    public function dayInsurances()
    {
        return $this->hasMany(\App\Models\EventTemplateDayInsurance::class);
    }

    /**
     * Pobierz ubezpieczenie dla danego dnia (lub null)
     */
    public function getInsuranceForDay($day)
    {
        return $this->dayInsurances()->where('day', $day)->first()?->insurance;
    }

    /**
     * Relacja wiele-do-jednego z tabelą bus
     */
    public function bus()
    {
        return $this->belongsTo(Bus::class);
    }

    /**
     * Relacja dni hotelowych (noclegów) dla eventu
     */
    public function hotelDays()
    {
        return $this->hasMany(EventTemplateHotelDay::class);
    }

    /**
     * Relacja wiele-do-jednego z tabelą markup
     */
    public function markup()
    {
        return $this->belongsTo(Markup::class);
    }

    /**
     * Relacja jeden-do-wielu z imprezami utworzonymi z tego szablonu
     */
    public function events()
    {
        return $this->hasMany(Event::class);
    }

    /**
     * Scope dla aktywnych szablonów
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope dla nieaktywnych szablonów
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeWithFrontendRelations($query)
    {
        return $query->with([
            'tags',
            'programPoints',
            'startingPlaceAvailabilities.startPlace',
            'eventTypes',
            'transportTypes',
            'pricesPerPerson.eventTemplateQty',
            'pricesPerPerson.currency',
            'pricesPerPerson.startPlace',
        ]);
    }

    /**
     * Identyfikatory punktów startowych (podstawienia) dostępnych dla tego szablonu.
     * Tylko miejsca z availability available=true (bez fallbacku do wszystkich startowych).
     *
     * @return Collection<int, int>
     */
    public function resolveAvailableStartPlaceIds(): Collection
    {
        $baseQuery = Place::query()->startingPlaces();

        if (! Schema::hasTable('event_template_starting_place_availability')) {
            return $baseQuery->pluck('id');
        }

        $configured = $this->startingPlaceAvailabilities()
            ->where('available', true)
            ->pluck('start_place_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($configured->isEmpty()) {
            return collect();
        }

        return $baseQuery->whereIn('id', $configured)->pluck('id');
    }

    /**
     * Relacja jeden-do-wielu z dostępnością miejsc startowych
     */
    public function startingPlaceAvailabilities()
    {
        return $this->hasMany(\App\Models\EventTemplateStartingPlaceAvailability::class);
    }

    /**
     * Scope: dostępność dla konkretnego miejsca startu (available=1)
     */
    public function scopeWithAvailabilityFor($query, int $startPlaceId)
    {
        return $query->whereHas('startingPlaceAvailabilities', function ($q) use ($startPlaceId) {
            $q->where('start_place_id', $startPlaceId)->where('available', true);
        });
    }

    /**
     * Scope: posiada lokalną cenę >0 dla miejsca startu
     */
    public function scopeWithLocalPriceFor($query, int $startPlaceId)
    {
        return $query->whereHas('pricesPerPerson', function ($q) use ($startPlaceId) {
            $q->where('start_place_id', $startPlaceId)->where('price_per_person', '>', 0);
        });
    }

    /**
     * Scope łączący availability + lokalną cenę (strict local mode)
     */
    public function scopeStrictLocalFor($query, int $startPlaceId)
    {
        return $query->withAvailabilityFor($startPlaceId)->withLocalPriceFor($startPlaceId);
    }

    public function isForeignTrip(): bool
    {
        $this->loadMissing('eventTypes');

        if (! $this->relationLoaded('eventTypes') || $this->eventTypes === null) {
            return false;
        }

        $foreignNames = [
            'zagraniczne',
            'krajowe z wyjazdem za granicę',
        ];

        return $this->eventTypes->contains(function ($type) use ($foreignNames) {
            $name = Str::lower($type->name ?? '');

            return in_array($name, $foreignNames, true);
        });
    }

    /**
     * Clone the event template with all relations
     */
    public function cloneWithRelations($newName = null)
    {
        // Start transaction for data integrity
        DB::beginTransaction();

        try {
            // Clone main template
            $clone = $this->replicate();
            $clone->name = $newName ?: $this->name.' (Copy)';
            $clone->slug = $this->slug.'-copy-'.time();
            $clone->save();

            // Clone starting places availability
            foreach ($this->startingPlaceAvailabilities as $availability) {
                $clone->startingPlaceAvailabilities()->create([
                    'start_place_id' => $availability->start_place_id,
                    'end_place_id' => $availability->end_place_id,
                    'available' => $availability->available,
                    'note' => $availability->note,
                ]);
            }

            // Clone other relations if needed (program points, prices, etc.)
            // ... add similar cloning for other relations ...

            DB::commit();

            return $clone;
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Get available templates for a specific start place
     */
    public static function getAvailableForStartPlace($startPlaceId)
    {
        return self::whereHas('startingPlaceAvailabilities', function ($query) use ($startPlaceId) {
            $query->where('start_place_id', $startPlaceId)
                ->where('available', true);
        })->get();
    }

    /**
     * Relacja wiele-do-wielu z podatkami
     */
    public function taxes()
    {
        return $this->belongsToMany(Tax::class, 'event_template_tax');
    }

    /**
     * Build canonical pretty URL for this event template.
     * Pattern: /{region-name}/{duration}-dniowe/{id}/{slug}
     * Region currently derived from cookie/default (no Region model present) -> 'region'
     */
    public function prettyUrl(?int $startPlaceId = null): string
    {
        $effectiveStartPlaceId = $startPlaceId
            ?: (request()->cookie('start_place_id') ? (int) request()->cookie('start_place_id') : null)
            ?: (isset($GLOBALS['current_start_place_id']) ? (int) $GLOBALS['current_start_place_id'] : null);

        // Jeśli mamy jawny startPlaceId (z parametru lub cookie/global), użyj helpera.
        if ($effectiveStartPlaceId) {
            $regionSlug = Region::slugForLinks($effectiveStartPlaceId);
        } elseif (function_exists('request') && request()->route('regionSlug')) {
            // Zachowaj istniejące zachowanie: jeśli trasa ma regionSlug -> użyj go (fallback dla istniejących wywołań)
            $regionSlug = request()->route('regionSlug');
        } else {
            // Brak start place i brak parametru trasy -> użyj helpera bez parametru (domyślnie Warszawa lub rekord z bazy)
            $regionSlug = Region::slugForLinks(null);
        }

        $dayLength = ($this->duration_days ?? 0).'-dniowe';
        $slug = $this->slug ?: Str::slug($this->name);

        return route('package.pretty', compact('regionSlug', 'dayLength', 'slug') + ['id' => $this->id]);
    }
}
