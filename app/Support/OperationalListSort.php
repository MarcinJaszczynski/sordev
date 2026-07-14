<?php

namespace App\Support;

use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Domyślne sortowanie list operacyjnych: najnowsza aktywność (updated_at, potem created_at).
 */
final class OperationalListSort
{
    public const DEFAULT_COLUMN = 'updated_at';

    public const DEFAULT_DIRECTION = 'desc';

    public static function applyToQuery(Builder $query): Builder
    {
        return $query
            ->orderByDesc(self::DEFAULT_COLUMN)
            ->orderByDesc('created_at');
    }

    public static function applyToTable(Table $table): Table
    {
        return $table->defaultSort(self::DEFAULT_COLUMN, self::DEFAULT_DIRECTION);
    }
}
