<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaqEntry extends Model
{
    public const SCOPE_GLOBAL = 'global';

    public const SCOPE_HOME = 'home';

    public const SCOPE_ABOUT = 'about';

    public const SCOPE_FAQ_PAGE = 'faq_page';

    public const SCOPE_PACKAGE = 'package';

    public const CATEGORY_GENERAL = 'ogolne';

    public const CATEGORY_SCHOOL = 'szkolne';

    public const CATEGORY_CORPORATE = 'firmowe';

    public const CATEGORY_PAYMENTS = 'platnosci';

    public const CATEGORY_INSURANCE = 'ubezpieczenia';

    public const CATEGORY_TRANSPORT = 'transport';

    public const CATEGORY_BOOKING = 'rezerwacja';

    protected $fillable = [
        'question',
        'answer',
        'category',
        'scope',
        'event_template_id',
        'sort_order',
        'is_published',
        'include_in_schema',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'include_in_schema' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function eventTemplate(): BelongsTo
    {
        return $this->belongsTo(EventTemplate::class);
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeForScope($query, string $scope)
    {
        return $query->where(function ($q) use ($scope) {
            $q->where('scope', $scope)
                ->orWhere('scope', self::SCOPE_GLOBAL);
        });
    }

    public function scopeForEventTemplate($query, ?int $eventTemplateId)
    {
        return $query->where(function ($q) use ($eventTemplateId) {
            $q->whereNull('event_template_id');
            if ($eventTemplateId) {
                $q->orWhere('event_template_id', $eventTemplateId);
            }
        });
    }

    public static function categoryLabels(): array
    {
        return [
            self::CATEGORY_GENERAL => 'Ogólne',
            self::CATEGORY_SCHOOL => 'Wycieczki szkolne',
            self::CATEGORY_CORPORATE => 'Wyjazdy firmowe',
            self::CATEGORY_PAYMENTS => 'Płatności',
            self::CATEGORY_INSURANCE => 'Ubezpieczenia',
            self::CATEGORY_TRANSPORT => 'Transport',
            self::CATEGORY_BOOKING => 'Rezerwacja',
        ];
    }

    public static function scopeLabels(): array
    {
        return [
            self::SCOPE_GLOBAL => 'Globalne (wszędzie)',
            self::SCOPE_HOME => 'Strona główna',
            self::SCOPE_ABOUT => 'O nas',
            self::SCOPE_FAQ_PAGE => 'Strona FAQ',
            self::SCOPE_PACKAGE => 'Oferty wycieczek',
        ];
    }

    public function categoryLabel(): string
    {
        return self::categoryLabels()[$this->category] ?? $this->category;
    }
}
