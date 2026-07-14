<?php

namespace App\Filament\Forms;

use App\Support\PhoneValidation;
use Filament\Forms\Components\TextInput;

/**
 * Pole telefonu bez restrykcyjnego ->tel() Filament (blokuje wiele formatów międzynarodowych).
 */
final class PhoneInput
{
    public static function make(string $name): TextInput
    {
        return TextInput::make($name)
            ->maxLength(PhoneValidation::MAX_LENGTH)
            ->rules(PhoneValidation::optionalRules())
            ->helperText('Format międzynarodowy, np. +48 606 102 243 lub +44 7700 900123');
    }
}
