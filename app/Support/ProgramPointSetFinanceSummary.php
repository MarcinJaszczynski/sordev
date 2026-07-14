<?php

namespace App\Support;

final class ProgramPointSetFinanceSummary
{
    /**
     * @param  array<int, string>  $payerLines
     * @param  array<int, string>  $paymentDueLines
     */
    public function __construct(
        public string $calcLabel,
        public string $plannedLabel,
        public string $paidLabel,
        public string $paidStatus,
        public ?string $advanceHtml,
        public ?string $advanceLabel,
        public array $payerLines = [],
        public string $settlementInfoHtml = '',
        public array $paymentDueLines = [],
        public bool $isSetRollup = true,
        public int $paymentCount = 0,
        public bool $hasPilotShare = false,
        public bool $hasOfficeShare = false,
        public bool $hasSettlement = false,
    ) {}
}
