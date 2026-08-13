<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class EventPackageDocument extends Model
{
    public const AUDIENCE_PILOT = 'pilot';

    public const AUDIENCE_HOTEL = 'hotel';

    public const AUDIENCE_DRIVER = 'driver';

    public const AUDIENCE_FOLDER = 'folder';

    public const EDIT_LIVE = 'live';

    public const EDIT_OVERRIDES = 'overrides';

    public const EDIT_FROZEN = 'frozen';

    public const EDIT_UPLOAD = 'upload';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_READY = 'ready';

    /** @var list<string> */
    public const AUDIENCES = [
        self::AUDIENCE_PILOT,
        self::AUDIENCE_HOTEL,
        self::AUDIENCE_DRIVER,
        self::AUDIENCE_FOLDER,
    ];

    public static array $audienceLabels = [
        self::AUDIENCE_PILOT => 'Pakiet pilota',
        self::AUDIENCE_HOTEL => 'Pakiet hotelu',
        self::AUDIENCE_DRIVER => 'Teczka kierowcy',
        self::AUDIENCE_FOLDER => 'Teczka imprezy',
    ];

    public static array $editModeLabels = [
        self::EDIT_LIVE => 'Z danych imprezy',
        self::EDIT_OVERRIDES => 'Z poprawkami',
        self::EDIT_FROZEN => 'Zamrożony snapshot',
        self::EDIT_UPLOAD => 'Wgrany PDF',
    ];

    public static array $statusLabels = [
        self::STATUS_DRAFT => 'Szkic',
        self::STATUS_READY => 'Gotowy',
    ];

    public static array $hideSectionLabels = [
        'hotel_plan' => 'Plan noclegów / pokoje',
        'program' => 'Program imprezy',
        'pilot_set_finance' => 'Finanse zestawu pilota',
        'notes' => 'Notatki',
        'attachments_list' => 'Lista załączników w PDF',
    ];

    protected $fillable = [
        'event_id',
        'audience',
        'edit_mode',
        'status',
        'overrides',
        'frozen_html',
        'upload_path',
        'notes',
        'generated_at',
        'created_by',
    ];

    protected $casts = [
        'overrides' => 'array',
        'generated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (Auth::check()) {
                $model->created_by ??= Auth::id();
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function usesUploadedPdf(): bool
    {
        return $this->edit_mode === self::EDIT_UPLOAD && filled($this->upload_path);
    }

    public function usesFrozenHtml(): bool
    {
        return $this->edit_mode === self::EDIT_FROZEN && filled($this->frozen_html);
    }

    public function resolveUploadAbsolutePath(): ?string
    {
        if (blank($this->upload_path)) {
            return null;
        }

        $absolute = Storage::disk('public')->path($this->upload_path);

        return is_file($absolute) ? $absolute : null;
    }

    /**
     * @return array{intro_html: ?string, extra_notes_html: ?string, hide_sections: list<string>, custom_labels: array<string, string>}
     */
    public function normalizedOverrides(): array
    {
        $raw = is_array($this->overrides) ? $this->overrides : [];

        return [
            'intro_html' => isset($raw['intro_html']) ? (string) $raw['intro_html'] : null,
            'extra_notes_html' => isset($raw['extra_notes_html']) ? (string) $raw['extra_notes_html'] : null,
            'hide_sections' => array_values(array_filter(
                array_map('strval', $raw['hide_sections'] ?? []),
                fn (string $key): bool => $key !== ''
            )),
            'custom_labels' => is_array($raw['custom_labels'] ?? null)
                ? array_map('strval', $raw['custom_labels'])
                : [],
        ];
    }
}
