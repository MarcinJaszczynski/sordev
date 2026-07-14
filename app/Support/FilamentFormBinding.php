<?php

namespace App\Support;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * Domyślne powiązanie pól formularza Filament — debounced live zamiast onBlur/defer.
 * Zapobiega gubieniu znaków przy szybkim pisaniu i zapisie bez opuszczenia pola.
 */
final class FilamentFormBinding
{
    public const DEBOUNCE_MS = 500;

    public static function apply(): void
    {
        $configure = static function (TextInput|Textarea|RichEditor $component): void {
            $component->live(debounce: self::DEBOUNCE_MS);
        };

        TextInput::configureUsing($configure, isImportant: true);
        Textarea::configureUsing($configure, isImportant: true);
        RichEditor::configureUsing($configure, isImportant: true);

        if (class_exists(\FilamentTiptapEditor\TiptapEditor::class)) {
            \FilamentTiptapEditor\TiptapEditor::configureUsing(
                static fn (\FilamentTiptapEditor\TiptapEditor $editor) => $editor->live(debounce: self::DEBOUNCE_MS),
                isImportant: true,
            );
        }
    }
}
