<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventResource\Concerns;

/**
 * Strony dawnego hubu „Operacje” — teraz flat w primary record sub-navigation.
 */
trait HasEventOperationsSubNavigation
{
    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return true;
    }

    /**
     * @return array<int|string, string>
     */
    protected function buildModuleBreadcrumbs(): array
    {
        return $this->eventRecordBreadcrumbs(
            sectionLabel: static::getNavigationLabel() ?? static::$title,
        );
    }
}
