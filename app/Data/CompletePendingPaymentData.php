<?php

declare(strict_types=1);

namespace App\Data;

readonly class CompletePendingPaymentData
{
    public function __construct(
        public string $rowId,
    ) {}
}
