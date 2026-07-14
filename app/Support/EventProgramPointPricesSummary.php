<?php

namespace App\Support;

final class EventProgramPointPricesSummary
{
    public const STATUS_NONE = 'none';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_FULL = 'full';

    public static function resolvePaidStatus(float $paidAmount, float $plannedAmount): string
    {
        if ($plannedAmount <= 0.00001) {
            return $paidAmount > 0.00001 ? self::STATUS_PARTIAL : self::STATUS_NONE;
        }

        if ($paidAmount >= ($plannedAmount - 0.01)) {
            return self::STATUS_FULL;
        }

        if ($paidAmount > 0.00001) {
            return self::STATUS_PARTIAL;
        }

        return self::STATUS_NONE;
    }
}
