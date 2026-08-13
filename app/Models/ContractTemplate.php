<?php

namespace App\Models;

use App\Services\AgreementPlaceholderCatalog;
use App\Support\AgreementHtml;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Model ContractTemplate
 * Reprezentuje szablon umowy w systemie.
 *
 * @property int $id
 * @property string $name
 * @property string $content
 * @property array<int, array{key: string, label: string, default?: string|null}>|null $custom_placeholders
 * @property array<int, string>|null $applies_to
 * @property string|null $version_group_id
 * @property int $version
 * @property bool $is_active
 * @property string|null $version_notes
 * @property array<int, string>|null $default_attachments
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class ContractTemplate extends Model
{
    use HasFactory;

    public const APPLIES_GROUP = 'group';

    public const APPLIES_INDIVIDUAL = 'individual';

    public const APPLIES_ANNEX = 'annex';

    public const APPLIES_CUSTOM = 'custom';

    /**
     * @var array<string, string>
     */
    public static array $appliesToOptions = [
        self::APPLIES_GROUP => 'Grupowa',
        self::APPLIES_INDIVIDUAL => 'Indywidualna',
        self::APPLIES_ANNEX => 'Aneks',
        self::APPLIES_CUSTOM => 'Umowa własna (z szablonu)',
    ];

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'content',
        'custom_placeholders',
        'applies_to',
        'version_group_id',
        'version',
        'is_active',
        'version_notes',
        'default_attachments',
    ];

    protected $casts = [
        'default_attachments' => 'array',
        'custom_placeholders' => 'array',
        'applies_to' => 'array',
        'version' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $template): void {
            $template->version_group_id ??= (string) Str::uuid();
            $template->version ??= 1;
            $template->is_active ??= true;
        });

        static::saving(function (self $template): void {
            $template->content = AgreementHtml::normalizeContent($template->content);
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Puste applies_to = szablon uniwersalny (pasuje do każdego typu).
     */
    public function appliesToType(?string $agreementType, bool $isAnnex = false): bool
    {
        $applies = $this->applies_to;

        if (! is_array($applies) || $applies === []) {
            return true;
        }

        $target = $isAnnex ? self::APPLIES_ANNEX : $agreementType;

        if (! filled($target) || $target === Contract::TYPE_TEMPLATE) {
            return true;
        }

        return in_array($target, $applies, true);
    }

    /**
     * Filtruje szablony pod typ umowy (puste applies_to = uniwersalny).
     * Filtrowanie w PHP — stabilne na MySQL i SQLite (testy).
     */
    public function scopeForAgreementType(Builder $query, ?string $agreementType, bool $isAnnex = false): Builder
    {
        // Scope zostawiony dla czytelności zapytań; właściwy filtr jest w optionsForSelect().
        return $query;
    }

    /**
     * @return list<array{key: string, label: string, default: string|null}>
     */
    public function normalizedCustomPlaceholders(): array
    {
        $result = [];

        foreach ((array) ($this->custom_placeholders ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $key = trim((string) ($row['key'] ?? ''));
            $key = trim($key, '[]');

            if ($key === '') {
                continue;
            }

            // Normalizacja: bez spacji, do użycia w [klucz]
            $key = preg_replace('/\s+/', '_', $key) ?? $key;

            $result[] = [
                'key' => $key,
                'label' => filled($row['label'] ?? null) ? (string) $row['label'] : $key,
                'default' => filled($row['default'] ?? null) ? (string) $row['default'] : null,
            ];
        }

        return $result;
    }

    public function displayLabel(): string
    {
        $label = $this->name.' (v'.$this->version.')';

        if (! $this->is_active) {
            $label .= ' — nieaktywny';
        }

        return $label;
    }

    /**
     * Opcje selecta: aktywne szablony, z filtrem typu.
     *
     * @return array<int, string>
     */
    public static function optionsForSelect(?string $agreementType = null, bool $isAnnex = false, bool $onlyActive = true): array
    {
        $query = static::query()->orderBy('name')->orderByDesc('version');

        if ($onlyActive) {
            $query->active();
        }

        return $query->get()
            ->filter(fn (self $template): bool => $template->appliesToType($agreementType, $isAnnex))
            ->mapWithKeys(fn (self $template): array => [$template->id => $template->displayLabel()])
            ->all();
    }

    public function createNewVersion(?string $notes = null): self
    {
        $clone = $this->replicate([
            'created_at',
            'updated_at',
        ]);

        $nextVersion = (int) static::query()
            ->where('version_group_id', $this->version_group_id)
            ->max('version') + 1;

        $clone->version = max(1, $nextVersion);
        $clone->version_notes = $notes;
        $clone->is_active = true;
        $clone->save();

        // Dezaktywuj poprzednie wersje w tej samej linii (stabilne: jedna aktywna na grupę).
        static::query()
            ->where('version_group_id', $this->version_group_id)
            ->where('id', '!=', $clone->id)
            ->update(['is_active' => false]);

        return $clone->fresh();
    }

    /**
     * @return array<string, string>
     */
    public function customPlaceholderSelectOptions(): array
    {
        $options = [];

        foreach ($this->normalizedCustomPlaceholders() as $definition) {
            $tag = '['.$definition['key'].']';
            $options[$tag] = 'Własne: '.$definition['label'].' '.$tag;
        }

        return $options;
    }

    /**
     * Połączone opcje pickerów (system + własne).
     *
     * @return array<string, string>
     */
    public function allInsertablePlaceholderOptions(?AgreementPlaceholderCatalog $catalog = null): array
    {
        $catalog ??= app(AgreementPlaceholderCatalog::class);

        return array_merge(
            $catalog->optionsForSelect(),
            $this->customPlaceholderSelectOptions(),
        );
    }
}
