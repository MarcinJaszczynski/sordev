<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventParticipantsSubNavigation;
use App\Filament\Resources\EventResource\Concerns\HasEventWorkflowContext;
use App\Models\Event;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

abstract class ManageEventParticipantsSection extends Page
{
    use HasEventParticipantsSubNavigation;
    use HasEventWorkflowContext {
        getWorkflowContext as protected getBaseWorkflowContext;
    }
    use InteractsWithRecord;

    protected static string $resource = EventResource::class;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(static::getResource()::canEdit($this->getRecord()), 403);
    }

    public function getSubNavigation(): array
    {
        return [];
    }

    public function getWorkflowContext(): ?array
    {
        $context = $this->getBaseWorkflowContext();

        if ($context === null) {
            return null;
        }

        $event = $this->record;
        $backLink = [
            'label' => 'Dane imprezy',
            'url' => EventResource::getUrl('edit', ['record' => $event->getKey()]),
            'icon' => 'heroicon-o-arrow-left',
        ];

        $context['links'] = array_merge(
            [$backLink],
            $context['links'] ?? [],
        );

        $code = filled($event->code ?? null) ? $event->code : '#'.$event->getKey();
        $context['subtitle'] = trim(($context['subtitle'] ?? '').' · Uczestnicy imprezy '.$code);

        return $context;
    }

    protected function currentEvent(): Event
    {
        /** @var Event $event */
        $event = $this->record;

        return $event;
    }

    public function getTitle(): string
    {
        $code = filled($this->record->code ?? null) ? $this->record->code : '#'.$this->record->getKey();
        $base = static::$title ?? 'Uczestnicy';

        return $base.' — impreza '.$code;
    }

    public static function getResourcePageName(): string
    {
        foreach (EventResource::getPages() as $pageName => $pageRegistration) {
            if ($pageRegistration->getPage() !== static::class) {
                continue;
            }

            return $pageName;
        }

        throw new \Exception('Page ['.static::class.'] is not registered to the resource ['.EventResource::class.'].');
    }

    public static function getRouteName(?string $panel = null): string
    {
        return EventResource::getRouteBaseName(panel: $panel).'.'.static::getResourcePageName();
    }
}
