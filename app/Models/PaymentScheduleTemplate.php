<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Globalny szablon harmonogramu wpłat (analogicznie do ContractTemplate).
 *
 * @property int $id
 * @property string $name
 * @property array<int, string>|null $applies_to
 * @property string|null $version_group_id
 * @property int $version
 * @property bool $is_active
 * @property string|null $version_notes
 */
class PaymentScheduleTemplate extends Model
{
    public const APPLIES_GROUP = 'group';

    public const APPLIES_INDIVIDUAL = 'individual';

    public const APPLIES_CUSTOM = 'custom';

    /** @var array<string, string> */
    public static array $appliesToOptions = [
        self::APPLIES_GROUP => 'Grupowa',
        self::APPLIES_INDIVIDUAL => 'Indywidualna',
        self::APPLIES_CUSTOM => 'Umowa własna',
    ];

    protected $fillable = [
        'name',
        'applies_to',
        'version_group_id',
        'version',
        'is_active',
        'version_notes',
    ];

    protected $casts = [
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
    }

    public function installments(): HasMany
    {
        return $this->hasMany(PaymentScheduleTemplateInstallment::class)
            ->orderBy('sort_order');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function appliesToType(?string $contractType): bool
    {
        $applies = $this->applies_to;

        if (! is_array($applies) || $applies === []) {
            return true;
        }

        if (! filled($contractType) || $contractType === Contract::TYPE_TEMPLATE) {
            return true;
        }

        return in_array($contractType, $applies, true);
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
     * @return array<int, string>
     */
    public static function optionsForSelect(?string $contractType = null, bool $onlyActive = true): array
    {
        $query = static::query()->orderBy('name')->orderByDesc('version');

        if ($onlyActive) {
            $query->active();
        }

        return $query->get()
            ->filter(fn (self $template): bool => $template->appliesToType($contractType))
            ->mapWithKeys(fn (self $template): array => [$template->id => $template->displayLabel()])
            ->all();
    }

    public function createNewVersion(?string $notes = null): self
    {
        $clone = $this->replicate(['created_at', 'updated_at']);
        $clone->version = (int) static::query()
            ->where('version_group_id', $this->version_group_id)
            ->max('version') + 1;
        $clone->version_notes = $notes;
        $clone->is_active = true;
        $clone->save();

        foreach ($this->installments as $installment) {
            $row = $installment->replicate(['created_at', 'updated_at']);
            $row->payment_schedule_template_id = $clone->id;
            $row->save();
        }

        static::query()
            ->where('version_group_id', $this->version_group_id)
            ->where('id', '!=', $clone->id)
            ->update(['is_active' => false]);

        return $clone->fresh(['installments']);
    }
}
