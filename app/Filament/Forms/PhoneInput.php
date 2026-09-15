<?php

namespace App\Filament\Forms;

use App\Support\PhoneValidation;
use Filament\Forms\Components\TextInput;

/**
 * Pole telefonu bez restrykcyjnego ->tel() Filament (blokuje wiele formatów międzynarodowych).
 * Sanityzacja przed walidacją i przy zapisie — NBSP / en-dash z wklejek nie blokują numerów +XX.
 */
final class PhoneInput
{
    public static function make(string $name): TextInput
    {
        $sanitize = static fn (mixed $state): mixed => is_string($state)
            ? PhoneValidation::sanitize($state)
            : $state;

        return TextInput::make($name)
            ->maxLength(PhoneValidation::MAX_LENGTH)
            ->rules(PhoneValidation::optionalRules())
            ->mutateStateForValidationUsing($sanitize)
            ->dehydrateStateUsing($sanitize)
            ->helperText('Format międzynarodowy, np. +48 606 102 243 lub +44 7700 900123');
    }
}
