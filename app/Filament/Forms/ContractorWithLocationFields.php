<?php

namespace App\Filament\Forms;

use App\Models\ContractorLocation;
use App\Services\ContractorLocationService;
use Filament\Forms;
use Filament\Forms\Components\Component;
use Filament\Forms\Get;
use Illuminate\Support\Facades\Schema;

final class ContractorWithLocationFields
{
    /**
     * @param  array<int, Component>  $contractorFields
     * @return array<int, Component>
     */
    public static function append(
        array $contractorFields,
        string $contractorField = 'contractor_id',
        string $locationField = 'contractor_location_id',
        int|string|array|null $columnSpan = null,
    ): array {
        if (! Schema::hasTable('contractor_locations') || ! Schema::hasColumn('event_program_points', 'contractor_location_id')) {
            return $contractorFields;
        }

        $locationService = app(ContractorLocationService::class);

        $locationSelect = Forms\Components\Select::make($locationField)
            ->label('Miejsce prowadzenia działalności')
            ->searchable()
            ->nullable()
            ->live()
            ->visible(fn (Get $get): bool => $locationService->contractorRequiresLocationSelection(
                filled($get($contractorField)) ? (int) $get($contractorField) : null,
            ))
            ->required(fn (Get $get): bool => $locationService->contractorRequiresLocationSelection(
                filled($get($contractorField)) ? (int) $get($contractorField) : null,
            ))
            ->options(fn (Get $get) => $locationService->optionsForContractor(
                contractorId: filled($get($contractorField)) ? (int) $get($contractorField) : null,
                includeId: filled($get($locationField)) ? (int) $get($locationField) : null,
            ))
            ->getSearchResultsUsing(fn (string $search, Get $get) => $locationService->optionsForContractor(
                contractorId: filled($get($contractorField)) ? (int) $get($contractorField) : null,
                includeId: filled($get($locationField)) ? (int) $get($locationField) : null,
                search: $search,
            ))
            ->getOptionLabelUsing(function ($value): ?string {
                if (! $value) {
                    return null;
                }

                return ContractorLocation::query()->find($value)?->shortLabel();
            })
            ->helperText('Adres podjazdu dla pilota i programu — nie adres rozliczeniowy firmy.')
            ->rule(function (Get $get) use ($contractorField, $locationService): \Closure {
                return function (string $attribute, $value, \Closure $fail) use ($get, $contractorField, $locationService): void {
                    if (! $value) {
                        return;
                    }

                    $contractorId = filled($get($contractorField)) ? (int) $get($contractorField) : null;

                    if (! $locationService->validateLocationBelongsToContractor($contractorId, (int) $value)) {
                        $fail('Wybrane miejsce nie należy do tego kontrahenta.');
                    }
                };
            });

        if ($columnSpan !== null) {
            $locationSelect->columnSpan($columnSpan);
        }

        return [
            ...$contractorFields,
            $locationSelect,
        ];
    }
}
