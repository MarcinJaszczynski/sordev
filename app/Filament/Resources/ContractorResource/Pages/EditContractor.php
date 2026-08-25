<?php

namespace App\Filament\Resources\ContractorResource\Pages;

use App\Filament\Resources\ContractorResource;
use App\Services\ContractorInvolvementOverviewService;
use App\Support\ContractorContactDetails;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContractor extends EditRecord
{
    protected static string $resource = ContractorResource::class;

    protected static string $view = 'filament.resources.contractor-resource.pages.edit-contractor';

    /** @var 'dane'|'udzial' */
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
        if (! in_array($tab, ['dane', 'udzial'], true)) {
            return;
        }

        $this->contractorTab = $tab;
    }

    public function setInvolvementKind(string $kind): void
    {
        if (! in_array($kind, ['all', 'event', 'point', 'cost', 'invoice'], true)) {
            return;
        }

        $this->involvementKind = $kind;
        $this->contractorTab = 'udzial';
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
     *     overview: array<string, mixed>
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
