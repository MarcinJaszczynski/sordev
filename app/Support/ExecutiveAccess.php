<?php

namespace App\Support;

use App\Models\EventSettlement;
use App\Models\User;

final class ExecutiveAccess
{
    /** @return list<string> */
    public static function ownerRoles(): array
    {
        return config('executive.owner_roles', ['super_admin', 'admin', 'wlasciciel']);
    }

    /** @return list<string> */
    public static function statisticsRoles(): array
    {
        return config('executive.statistics_roles', ['super_admin', 'admin', 'wlasciciel']);
    }

    /**
     * P&L, zysk uznany, wyniki końcowe zamkniętego rozliczenia.
     */
    public static function canViewFinalFinancialResults(?User $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user) {
            return false;
        }

        if (config('executive.open_access', false)) {
            return true;
        }

        return $user->hasRole(self::ownerRoles());
    }

    public static function canAccessProfitLossPanel(?User $user = null): bool
    {
        return self::canViewFinalFinancialResults($user);
    }

    public static function canAccessStatisticsPanel(?User $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user) {
            return false;
        }

        if (config('executive.open_access', false)) {
            return true;
        }

        return $user->hasRole(self::statisticsRoles());
    }

    /**
     * Raporty portfolio (wydatki, rezerwacje, pulpit finansowy, raport finansowy).
     * Ukryte przed biurem — widoczne dla admin / super_admin / właściciel.
     */
    public static function canAccessSensitiveAnalytics(?User $user = null): bool
    {
        return self::canAccessStatisticsPanel($user);
    }

    public static function canViewSettlementFinancialSummary(EventSettlement $settlement, ?User $user = null): bool
    {
        if (self::canViewFinalFinancialResults($user)) {
            return true;
        }

        return ! in_array($settlement->status, ['closed'], true);
    }

    public static function settlementSummaryRestrictedMessage(): string
    {
        return 'Końcowy wynik finansowy jest dostępny wyłącznie dla właściciela.';
    }
}
