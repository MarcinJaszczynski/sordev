<?php

namespace App\Enums;

enum TaskSource: string
{
    case Office = 'office';
    case PilotChecklist = 'pilot_checklist';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Office => 'Zadanie biura',
            self::PilotChecklist => 'Checklista pilota',
            self::System => 'Systemowe',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
