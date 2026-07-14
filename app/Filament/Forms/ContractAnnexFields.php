<?php

namespace App\Filament\Forms;

use App\Models\Contract;
use Filament\Forms;
use Filament\Forms\Get;
use Illuminate\Support\Arr;

class ContractAnnexFields
{
    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function createSchema(): array
    {
        return static::sharedSchema(requireChangeTypes: true);
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function editSchema(): array
    {
        return static::sharedSchema(requireChangeTypes: false);
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected static function sharedSchema(bool $requireChangeTypes): array
    {
        return [
            Forms\Components\CheckboxList::make('annex_change_types')
                ->label('Rodzaje zmian w aneksie')
                ->options(Contract::$annexChangeTypes)
                ->columns(2)
                ->live()
                ->required($requireChangeTypes)
                ->helperText('Zaznacz, czego dotyczy aneks. Przy zmianie programu zapisujemy aktualny program imprezy.'),

            Forms\Components\Textarea::make('annex_program_change_notes')
                ->label('Opis zmian w programie')
                ->rows(4)
                ->columnSpanFull()
                ->visible(fn (Get $get): bool => static::includesProgramChange($get('annex_change_types')))
                ->helperText('Krótki opis różnic względem programu z umowy pierwotnej.'),

            Forms\Components\Select::make('body_edit_mode')
                ->label('Sposób przygotowania treści aneksu')
                ->options([
                    Contract::BODY_EDIT_TEMPLATE => 'Z szablonu umowy',
                    Contract::BODY_EDIT_MANUAL => 'Ręczna edycja treści',
                ])
                ->default(Contract::BODY_EDIT_TEMPLATE)
                ->live()
                ->required(),

            \FilamentTiptapEditor\TiptapEditor::make('agreement_body')
                ->label('Treść aneksu')
                ->columnSpanFull()
                ->visible(fn (Get $get): bool => ($get('body_edit_mode') ?? Contract::BODY_EDIT_TEMPLATE) === Contract::BODY_EDIT_MANUAL)
                ->required(fn (Get $get): bool => ($get('body_edit_mode') ?? null) === Contract::BODY_EDIT_MANUAL),
        ];
    }

    public static function includesProgramChange(mixed $changeTypes): bool
    {
        return in_array(Contract::ANNEX_CHANGE_PROGRAM, Arr::wrap($changeTypes), true);
    }
}
