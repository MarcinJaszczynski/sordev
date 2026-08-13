<?php

namespace App\Filament\Forms;

use App\Models\ContractTemplate;
use Filament\Forms;
use Filament\Forms\Get;

/**
 * Dynamiczne pola własne szablonu przy tworzeniu / edycji umowy.
 */
final class ContractTemplateCustomValueFields
{
    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function schema(string $templateIdField = 'contract_template_id'): array
    {
        return [
            Forms\Components\Section::make('Pola własne szablonu')
                ->description('Wartości podstawiane w znaczniki [klucz] z wybranego szablonu.')
                ->visible(function (Get $get) use ($templateIdField): bool {
                    $templateId = $get($templateIdField);
                    if (! filled($templateId)) {
                        return false;
                    }

                    $template = ContractTemplate::query()->find((int) $templateId);

                    return $template !== null && $template->normalizedCustomPlaceholders() !== [];
                })
                ->schema([
                    Forms\Components\Group::make()
                        ->statePath('custom_placeholder_values')
                        ->schema(function (Get $get) use ($templateIdField): array {
                            $templateId = $get($templateIdField);
                            if (! filled($templateId)) {
                                return [];
                            }

                            $template = ContractTemplate::query()->find((int) $templateId);
                            if (! $template) {
                                return [];
                            }

                            $fields = [];
                            foreach ($template->normalizedCustomPlaceholders() as $definition) {
                                $fields[] = Forms\Components\TextInput::make($definition['key'])
                                    ->label($definition['label'].' ['.$definition['key'].']')
                                    ->default($definition['default'])
                                    ->maxLength(500);
                            }

                            return $fields;
                        })
                        ->columns(['default' => 1, 'md' => 2]),
                ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function mergeIntoMeta(array $data): array
    {
        if (! array_key_exists('custom_placeholder_values', $data)) {
            return $data;
        }

        $values = $data['custom_placeholder_values'] ?? [];
        unset($data['custom_placeholder_values']);

        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];

        $normalized = collect(is_array($values) ? $values : [])
            ->map(fn ($value) => is_scalar($value) || $value === null ? (string) ($value ?? '') : '')
            ->filter(fn (string $value): bool => $value !== '')
            ->all();

        if ($normalized === []) {
            unset($meta['custom_placeholder_values']);
        } else {
            $meta['custom_placeholder_values'] = $normalized;
        }

        $data['meta'] = $meta;

        return $data;
    }

    /**
     * @param  array<string, mixed>|null  $meta
     * @return array<string, string>
     */
    public static function valuesFromMeta(?array $meta): array
    {
        $values = data_get($meta ?? [], 'custom_placeholder_values', []);

        return is_array($values) ? array_map('strval', $values) : [];
    }
}
