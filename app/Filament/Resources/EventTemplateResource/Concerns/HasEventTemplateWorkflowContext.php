<?php

namespace App\Filament\Resources\EventTemplateResource\Concerns;

use App\Filament\Concerns\HasWorkflowRecordContext;
use App\Filament\Resources\EventResource;
use App\Models\EventTemplate;

trait HasEventTemplateWorkflowContext
{
    use HasWorkflowRecordContext;

    public function getWorkflowContext(): ?array
    {
        if (! isset($this->record) || ! $this->record instanceof EventTemplate) {
            return null;
        }

        /** @var EventTemplate $template */
        $template = $this->record;

        return [
            'type' => 'Szablon imprezy',
            'title' => $template->name,
            'subtitle' => $template->subtitle,
            'status' => $template->is_active ? 'Aktywny' : 'Nieaktywny',
            'statusColor' => $template->is_active ? 'success' : 'gray',
            'meta' => [
                ['label' => 'Dni', 'value' => (string) ($template->duration_days ?? 1)],
            ],
            'links' => [
                [
                    'label' => 'Podgląd oferty',
                    'url' => $template->prettyUrl(),
                    'icon' => 'heroicon-o-globe-alt',
                    'external' => true,
                ],
                [
                    'label' => 'Generuj imprezę',
                    'url' => EventResource::getUrl('create').'?template='.$template->id,
                    'icon' => 'heroicon-o-plus-circle',
                ],
                [
                    'label' => 'Lista szablonów',
                    'url' => \App\Filament\Resources\EventTemplateResource::getUrl('index'),
                    'icon' => 'heroicon-o-rectangle-stack',
                ],
            ],
        ];
    }
}
