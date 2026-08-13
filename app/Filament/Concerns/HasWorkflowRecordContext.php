<?php

namespace App\Filament\Concerns;

/**
 * Kontekst rekordu wyświetlany pod sub-nawigacją workspace.
 *
 * @phpstan-type WorkflowContext array{
 *     type: string,
 *     title: string,
 *     subtitle?: string|null,
 *     status?: string|null,
 *     statusColor?: string|null,
 *     links?: array<int, array{label: string, url?: string, wire_click?: string, icon?: string|null, external?: bool}>,
 *     meta?: array<int, array{label: string, value: string}>
 * }
 */
trait HasWorkflowRecordContext
{
    /** @return WorkflowContext|null */
    public function getWorkflowContext(): ?array
    {
        return null;
    }

    public function getWorkflowContextViewData(): ?array
    {
        return $this->getWorkflowContext();
    }

    protected function getViewData(): array
    {
        return array_merge(parent::getViewData(), [
            'workflowContext' => $this->getWorkflowContextViewData(),
        ]);
    }
}
