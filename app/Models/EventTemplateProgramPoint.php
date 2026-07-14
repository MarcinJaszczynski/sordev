<?php

namespace App\Models;

use App\Models\Concerns\HasStickyNotes;
use App\Models\Concerns\HasTasks;
use App\Services\UnifiedPriceCalculator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Model EventTemplateProgramPoint
 * Reprezentuje punkt programu szablonu wydarzenia.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int|null $parent_id
 * @property int|null $order
 * @property string|null $office_notes
 * @property string|null $pilot_notes
 * @property int|null $duration_hours
 * @property int|null $duration_minutes
 * @property string|null $featured_image
 * @property array|null $gallery_images
 * @property float|null $unit_price
 * @property int|null $group_size
 * @property int|null $currency_id
 * @property bool $convert_to_pln
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class EventTemplateProgramPoint extends Model
{
    /**
     * Mutator: zawsze zapisuje string lub null dla featured_image
     */
    public function setFeaturedImageAttribute($value)
    {
        Log::debug('[setFeaturedImageAttribute] Wejście:', ['value' => $value, 'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)]);
        if (is_array($value)) {
            // Jeśli array, weź pierwszy element jeśli istnieje
            $this->attributes['featured_image'] = isset($value[0]) && is_string($value[0]) ? $value[0] : null;
        } elseif (is_string($value)) {
            $this->attributes['featured_image'] = $value;
        } else {
            $this->attributes['featured_image'] = null;
        }
        Log::debug('[setFeaturedImageAttribute] Zapisano:', ['featured_image' => $this->attributes['featured_image']]);
    }

    use HasFactory, HasStickyNotes, HasTasks;

    /**
     * Mutator: zawsze zapisuje poprawny JSON array stringów dla gallery_images
     */
    public function setGalleryImagesAttribute($value)
    {
        if (is_array($value)) {
            // Filtruj tylko stringi i poprawne ścieżki
            $value = array_filter($value, fn ($v) => is_string($v) && preg_match('/\.(png|jpg|jpeg|webp|gif)$/i', $v));
            $this->attributes['gallery_images'] = json_encode(array_values($value), JSON_UNESCAPED_SLASHES);
        } elseif (is_string($value)) {
            // Jeśli string, spróbuj zdekodować i zapisać jako array
            $arr = json_decode($value, true);
            if (is_array($arr)) {
                $arr = array_filter($arr, fn ($v) => is_string($v) && preg_match('/\.(png|jpg|jpeg|webp|gif)$/i', $v));
                $this->attributes['gallery_images'] = json_encode(array_values($arr), JSON_UNESCAPED_SLASHES);
            } else {
                $this->attributes['gallery_images'] = json_encode([], JSON_UNESCAPED_SLASHES);
            }
        } else {
            $this->attributes['gallery_images'] = json_encode([], JSON_UNESCAPED_SLASHES);
        }
    }

    use HasFactory;

    /**
     * Mutator: zawsze zwraca tylko stringi (ścieżki plików) dla gallery_images
     */
    public function getGalleryImagesAttribute($value)
    {
        $array = is_array($value) ? $value : json_decode($value, true);
        if (! is_array($array)) {
            return [];
        }

        // Zwracaj tylko stringi (ścieżki plików)
        return array_values(array_filter($array, fn ($item) => is_string($item)));
    }

    /**
     * Mutator: zawsze zwraca string lub null dla featured_image
     */
    public function getFeaturedImageAttribute($value)
    {
        Log::debug('[getFeaturedImageAttribute] Odczyt:', ['value' => $value]);

        return is_string($value) ? $value : null;
    }

    /**
     * Pola masowo przypisywalne
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'parent_id',
        'order',
        'office_notes',
        'pilot_notes',
        'duration_hours',
        'duration_minutes',
        'featured_image',
        'featured_image_original_name',
        'gallery_images',
        'unit_price',
        'group_size',
        'currency_id',
        'convert_to_pln',
    ];

    /**
     * Rzutowanie pól na typy
     *
     * @var array<string, string>
     */
    protected $casts = [
        'featured_image' => 'string',
        'gallery_images' => 'array',
        'convert_to_pln' => 'boolean',
    ];

    /**
     * Relacja do waluty
     */
    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * Relacja wiele-do-wielu z tagami
     */
    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Akcesor dla featured_image URL
     */
    public function getFeaturedImageUrlAttribute()
    {
        if ($this->featured_image) {
            return asset('storage/'.$this->featured_image);
        }

        return null;
    }

    /**
     * Akcesor dla gallery_images URLs
     */
    public function getGalleryImageUrlsAttribute()
    {
        if ($this->gallery_images && is_array($this->gallery_images)) {
            return array_map(function ($image) {
                return asset('storage/'.$image);
            }, $this->gallery_images);
        }

        return [];
    }

    /**
     * Relacja wiele-do-wielu z szablonami wydarzeń
     */
    public function eventTemplates()
    {
        return $this->belongsToMany(EventTemplate::class, 'event_template_event_template_program_point')
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
            ])
            ->orderBy('event_template_event_template_program_point.day')
            ->orderBy('event_template_event_template_program_point.order');
    }

    /**
     * Relacja do punktów nadrzędnych
     */
    public function parents()
    {
        return $this->belongsToMany(self::class, 'event_template_program_point_parent', 'child_id', 'parent_id');
    }

    /**
     * Relacja do punktów podrzędnych
     */
    public function children()
    {
        return $this->belongsToMany(self::class, 'event_template_program_point_parent', 'parent_id', 'child_id')
            ->withPivot('order')
            ->orderBy('event_template_program_point_parent.order');
    }

    /**
     * Relacja do pivotu właściwości podpunktów programu dla danego szablonu
     */
    public function childPropertiesForTemplate($eventTemplateId)
    {
        return $this->belongsToMany(
            \App\Models\EventTemplate::class,
            'event_template_program_point_child_pivot',
            'program_point_child_id',
            'event_template_id'
        )
            ->wherePivot('event_template_id', $eventTemplateId)
            ->withPivot([
                'id',
                'include_in_program',
                'include_in_calculation',
                'active',
                'show_title_style',
                'show_description',
                'created_at',
                'updated_at',
            ]);
    }

    /**
     * Event który zostaje uruchomiony po zapisaniu modelu
     */
    public static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            // Debugowanie podczas zapisywania
            Log::info('Saving EventTemplateProgramPoint', [
                'id' => $model->id,
                'featured_image' => $model->featured_image,
                'gallery_images' => $model->gallery_images,
            ]);
        });

        static::saved(function ($model) {
            // Debugowanie po zapisaniu
            Log::info('Saved EventTemplateProgramPoint', [
                'id' => $model->id,
                'featured_image' => $model->featured_image,
                'gallery_images' => $model->gallery_images,
            ]);
        });
    }

    /**
     * Automatyczne przeliczanie cen po zapisaniu lub usunięciu punktu programu
     */
    protected static function booted()
    {
        static::saved(function ($programPoint) {
            foreach ($programPoint->eventTemplates as $template) {
                (new UnifiedPriceCalculator)->recalculateForTemplate($template);
            }
        });
        static::deleted(function ($programPoint) {
            foreach ($programPoint->eventTemplates as $template) {
                (new UnifiedPriceCalculator)->recalculateForTemplate($template);
            }
        });
    }
}
