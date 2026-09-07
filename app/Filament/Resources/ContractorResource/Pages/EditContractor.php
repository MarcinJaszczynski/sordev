<?php

namespace App\Filament\Resources\ContractorResource\Pages;

use App\Filament\Resources\ContractorResource;
use App\Filament\Resources\ContractorResource\RelationManagers\LegacyEventsRelationManager;
use App\Services\ContractorInvolvementOverviewService;
use App\Services\Legacy\LegacyContractorArchiveStats;
use App\Services\PilotContractorAssignmentService;
use App\Support\ContractorContactDetails;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Schema;

class EditContractor extends EditRecord
{
    protected static string $resource = ContractorResource::class;

    protected static string $view = 'filament.resources.contractor-resource.pages.edit-contractor';

    /** @var 'dane'|'udzial'|'archiwalne' */
    public string $contractorTab = 'dane';

    public string $involvementSearch = '';

    /** @var 'all'|'event'|'point'|'cost'|'invoice' */
    public string $involvementKind = 'all';

    /** @var 'all'|string */
    public string $involvementRole = 'all';

    /** @var 'all'|'open'|'paid' */
    public string $involvementStatus = 'all';

    /** @var array<string, mixed>|null */
    protected ?array $contractorOverviewCache = null;

    public function setContractorTab(string $tab): void
    {
        $allowed = ['dane', 'udzial'];
        if ($this->hasArchiveTab()) {
            $allowed[] = 'archiwalne';
        }

        if (! in_array($tab, $allowed, true)) {
            return;
        }

        $this->contractorTab = $tab;

        // Unikaj trzymania activeRelationManager z innej zakładki (Dane vs Archiwum).
        $activeKey = match ($tab) {
            'dane' => array_key_first($this->daneRelationManagers()),
            'archiwalne' => array_key_first($this->archiveRelationManagers()),
            default => null,
        };

        $this->activeRelationManager = $activeKey !== null ? (string) $activeKey : null;
    }

    public function setInvolvementKind(string $kind): void
    {
        if (! in_array($kind, ['all', 'event', 'point', 'cost', 'invoice'], true)) {
            return;
        }

        $this->involvementKind = $kind;
        $this->contractorTab = 'udzial';
    }

    public function hasArchiveTab(): bool
    {
        return Schema::hasTable('legacy_events');
    }

    /**
     * Relation managers pod zakładką „Dane” — bez archiwum.
     *
     * @return array<string|class-string, class-string>
     */
    public function daneRelationManagers(): array
    {
        return $this->filterRelationManagers(excludeLegacy: true);
    }

    /**
     * Relation managers pod zakładką „Imprezy archiwalne”.
     *
     * @return array<string|class-string, class-string>
     */
    public function archiveRelationManagers(): array
    {
        return $this->filterRelationManagers(onlyLegacy: true);
    }

    /**
     * @return array<string|class-string, class-string>
     */
    protected function filterRelationManagers(bool $excludeLegacy = false, bool $onlyLegacy = false): array
    {
        $filtered = [];

        foreach ($this->getRelationManagers() as $key => $manager) {
            $class = is_string($manager) ? $manager : (string) $key;
            $isLegacy = $class === LegacyEventsRelationManager::class
                || is_a($class, LegacyEventsRelationManager::class, true);

            if ($onlyLegacy && ! $isLegacy) {
                continue;
            }

            if ($excludeLegacy && $isLegacy) {
                continue;
            }

            $filtered[$key] = $manager;
        }

        return $filtered;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! ContractorResource::formTypesIncludePilot($data['types'] ?? null)) {
            $data['birth_date'] = null;
            $data['pesel'] = null;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        app(PilotContractorAssignmentService::class)
            ->syncPortalUserDemographicsFromContractorRecord($this->getRecord()->fresh());
    }

    public function getSubheading(): ?string
    {
        $summary = ContractorContactDetails::inlineSummary($this->getRecord());

        return $summary !== '' ? $summary : null;
    }

    /**
     * @return array{
     *     name: string,
     *     status: string,
     *     status_label: string,
     *     nip: ?string,
     *     phone: ?string,
     *     email: ?string,
     *     types: list<string>,
     *     overview: array<string, mixed>,
     *     archive: array<string, mixed>
     * }
     */
    public function contractorPageSummary(): array
    {
        $record = $this->getRecord();
        $record->loadMissing('types');

        $status = (string) ($record->status ?? 'active');
        $statusLabel = match ($status) {
            'active' => 'Aktywny',
            'inactive' => 'Nieaktywny',
            default => $status,
        };

        return [
            'name' => $record->displayLabel(),
            'status' => $status,
            'status_label' => $statusLabel,
            'nip' => filled($record->nip) ? (string) $record->nip : null,
            'phone' => filled($record->phone) ? (string) $record->phone : null,
            'email' => filled($record->email) ? (string) $record->email : null,
            'types' => $record->types
                ->pluck('name')
                ->filter()
                ->map(fn ($name) => (string) $name)
                ->values()
                ->all(),
            'overview' => $this->resolvedOverview(),
            'archive' => app(LegacyContractorArchiveStats::class)->forContractor($record),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolvedOverview(): array
    {
        if ($this->contractorOverviewCache !== null) {
            return $this->contractorOverviewCache;
        }

        return $this->contractorOverviewCache = app(ContractorInvolvementOverviewService::class)
            ->for($this->getRecord());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function filteredInvolvementRows(): array
    {
        $rows = $this->resolvedOverview()['rows'] ?? [];

        return app(ContractorInvolvementOverviewService::class)->filterUnifiedRows(
            $rows,
            $this->involvementSearch,
            $this->involvementKind,
            $this->involvementRole,
            $this->involvementStatus,
        );
    }

    /**
     * @return array<string, string>
     */
    public function involvementRoleOptions(): array
    {
        return array_merge(
            ['all' => 'Wszystkie powiązania'],
            ContractorInvolvementOverviewService::ROLE_LABELS,
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
