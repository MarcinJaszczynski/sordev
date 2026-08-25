<?php

namespace App\Filament\Forms;

use App\Models\Contractor;
use App\Models\ContractorType;
use App\Services\ContractorLookupService;
use Filament\Forms;
use Filament\Forms\Components\Component;
use Filament\Forms\Get;

final class TypedContractorSelect
{
    /**
     * @param  array<int, string>  $typeNames
     * @return array<int, Component>
     */
    public static function make(
        string $field,
        string $label,
        array $typeNames,
        string $searchAllField,
        ?string $defaultTypeOnCreate = null,
        ?string $helperText = null,
        ?callable $afterStateUpdated = null,
        mixed $default = null,
        int|string|array|null $columnSpan = null,
        ?callable $restrictToContractorIds = null,
        ?string $searchAllHelperText = null,
    ): array {
        $lookup = app(ContractorLookupService::class);

        $resolveRestrictedIds = function () use ($restrictToContractorIds): ?array {
            if ($restrictToContractorIds === null) {
                return null;
            }

            $ids = ($restrictToContractorIds)();

            return collect($ids)
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all();
        };

        $select = Forms\Components\Select::make($field)
            ->hiddenLabel()
            ->searchable()
            ->live()
            ->nullable()
            ->getSearchResultsUsing(function (string $search, Get $get) use ($lookup, $typeNames, $searchAllField, $field, $resolveRestrictedIds): array {
                return $lookup->searchOptions(
                    search: $search,
                    typeNames: $typeNames,
                    searchAll: (bool) $get($searchAllField),
                    includeId: filled($get($field)) ? (int) $get($field) : null,
                    restrictToIds: $resolveRestrictedIds(),
                );
            })
            ->getOptionLabelUsing(function ($value) use ($lookup): ?string {
                if (! $value) {
                    return null;
                }

                $contractor = Contractor::query()->find($value);

                return $contractor ? $lookup->formatOptionLabel($contractor) : null;
            })
            ->getOptionLabelsUsing(function (array $values) use ($lookup): array {
                return $lookup->optionsForIds($values);
            })
            ->createOptionForm([
                Forms\Components\TextInput::make('name')
                    ->label('Nazwa')
                    ->required()
                    ->maxLength(255),
                PhoneInput::make('phone')
                    ->label('Telefon')
                    ->nullable(),
                Forms\Components\TextInput::make('email')
                    ->label('E-mail')
                    ->email()
                    ->maxLength(255)
                    ->nullable(),
                Forms\Components\TextInput::make('bank_account')
                    ->label('Nr konta bankowego')
                    ->maxLength(64)
                    ->nullable()
                    ->visible(fn (): bool => \Illuminate\Support\Facades\Schema::hasColumn('contractors', 'bank_account')),
            ])
            ->createOptionUsing(function (array $data) use ($defaultTypeOnCreate): int {
                if (isset($data['bank_account']) && filled($data['bank_account'])) {
                    $data['bank_account'] = trim((string) $data['bank_account']);
                }

                $contractor = Contractor::create($data);

                if ($defaultTypeOnCreate !== null) {
                    $typeIds = ContractorType::idsForNames([$defaultTypeOnCreate]);

                    if ($typeIds !== []) {
                        $contractor->types()->syncWithoutDetaching($typeIds);
                    }
                }

                return $contractor->getKey();
            });

        if ($helperText !== null) {
            $select->helperText($helperText);
        }

        if ($afterStateUpdated !== null) {
            $select->afterStateUpdated($afterStateUpdated);
        }

        if ($default !== null) {
            $select->default($default);
        }

        $defaultSearchAllHelper = $restrictToContractorIds !== null
            ? 'Lista ograniczona do hoteli z planu noclegów tej imprezy. Zaznacz, gdy hotel ma źle przypisany typ.'
            : 'Domyślnie lista jest ograniczona do wybranych typów. Zaznacz, aby przeszukać wszystkie firmy w bazie.';

        $checkbox = Forms\Components\Checkbox::make($searchAllField)
            ->label('Szukaj we wszystkich kontrahentach')
            ->helperText($searchAllHelperText ?? $defaultSearchAllHelper)
            ->dehydrated(false)
            ->live();

        $fieldset = Forms\Components\Fieldset::make($label)
            ->schema([
                $checkbox,
                $select,
            ]);

        if ($columnSpan !== null) {
            $fieldset->columnSpan($columnSpan);
        }

        return [$fieldset];
    }
}
