<?php

namespace App\Support;

/**
 * Wspólna fabryka tabów modułowych (finanse, pilot trip, itp.).
 *
 * @phpstan-type WorkflowTab array{key: string, label: string, description?: string, url: string, icon?: string, badge?: ?string, active?: bool}
 */
final class WorkflowModuleNavigation
{
    /**
     * @param  array<int, WorkflowTab>  $tabs
     * @return array<int, WorkflowTab>
     */
    public static function markActive(array $tabs, ?string $activeKey): array
    {
        if ($activeKey === null) {
            return $tabs;
        }

        foreach ($tabs as &$tab) {
            $tab['active'] = ($tab['key'] ?? '') === $activeKey;
        }
        unset($tab);

        return $tabs;
    }

    /**
     * @param  array<int, WorkflowTab>  $tabs
     * @return array<int, WorkflowTab>
     */
    public static function withBadges(array $tabs, array $badgesByKey): array
    {
        foreach ($tabs as &$tab) {
            $key = $tab['key'] ?? '';
            if (array_key_exists($key, $badgesByKey) && $badgesByKey[$key] !== null) {
                $tab['badge'] = (string) $badgesByKey[$key];
            }
        }
        unset($tab);

        return $tabs;
    }
}
