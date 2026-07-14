<?php

namespace App\Filament\Concerns;

use Illuminate\Support\Facades\Schema;

trait RequiresUfgContractsTable
{
    public static function isUfgModuleEnabled(): bool
    {
        return Schema::hasTable('contracts');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::isUfgModuleEnabled();
    }

    public static function canViewAny(): bool
    {
        if (! static::isUfgModuleEnabled()) {
            return false;
        }

        return parent::canViewAny();
    }

    public static function canCreate(): bool
    {
        if (! static::isUfgModuleEnabled()) {
            return false;
        }

        return parent::canCreate();
    }
}
