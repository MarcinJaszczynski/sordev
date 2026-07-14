<?php

namespace App\Support;

final class PilotSetFinanceCard
{
    /**
     * @param  array<int, PilotSetFinanceMemberLine>  $memberLines
     */
    public function __construct(
        public int $parentId,
        public string $parentName,
        public int $day,
        public int $order,
        public bool $inProgram,
        public string $totalPilotDueLabel,
        public string $plannedPilotLabel,
        public bool $hasPilotObligation,
        public array $memberLines = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'parent_id' => $this->parentId,
            'parent_name' => $this->parentName,
            'day' => $this->day,
            'order' => $this->order,
            'in_program' => $this->inProgram,
            'total_pilot_due_label' => $this->totalPilotDueLabel,
            'planned_pilot_label' => $this->plannedPilotLabel,
            'has_pilot_obligation' => $this->hasPilotObligation,
            'member_lines' => array_map(
                fn (PilotSetFinanceMemberLine $line): array => $line->toArray(),
                $this->memberLines,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toHintArray(): array
    {
        return [
            'is_set_rollup' => true,
            'has_pilot_obligation' => $this->hasPilotObligation,
            'payer_label' => $this->hasPilotObligation ? 'Pilot płaci (set)' : 'Finanse setu',
            'total_pilot_due_label' => $this->totalPilotDueLabel,
            'planned_pilot_label' => $this->plannedPilotLabel,
            'member_lines' => array_map(
                fn (PilotSetFinanceMemberLine $line): array => $line->toArray(),
                $this->memberLines,
            ),
            'lines' => [],
            'payment_lines' => [],
        ];
    }
}
