<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\InteractsWithEventRecord;
use App\Filament\Resources\EventResource\RelationManagers\ProgramPointsRelationManager;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Services\EventProgramScheduleService;
use App\Services\HotelStayReservationSync;
use App\Services\ProgramPointReservationSync;
use App\Support\ProgramTimeSlots;
use Filament\Actions;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\HasRelationManagers;
use Filament\Resources\Pages\Page;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\MaxWidth;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

class EditEventProgram extends Page
{
    use HasRelationManagers;
    use InteractsWithEventRecord;

    #[Url(as: 'view', except: 'days')]
    public string $programView = 'days';

    #[Url(as: 'day', except: 1)]
    public int $programDay = 1;

    #[Url(as: 'filter', except: 'all')]
    public string $programFilter = 'all';

    public string $programDayStartTime = '08:00';

    public string $programDayRoute = '';

    protected static string $resource = EventResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.edit-event-program';

    protected static ?string $navigationLabel = 'Program';

    protected static ?string $title = 'Program';

    protected static ?string $navigationIcon = 'heroicon-o-bars-3';

    /**
     * @return array<int|string, string>
     */
    protected function buildModuleBreadcrumbs(): array
    {
        return $this->eventRecordBreadcrumbs(sectionLabel: 'Program');
    }

    protected ?string $maxContentWidth = 'full';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->authorizeAccess();
        $this->clampProgramDay();
        $this->syncProgramDayStartTimeProperty();
        $this->syncProgramDayRouteProperty();

        app(HotelStayReservationSync::class)->backfillForEvent($this->getRecord());
        app(ProgramPointReservationSync::class)->backfillForEvent($this->getRecord());

        if ($this->programView === 'planner') {
            $this->dispatchPlannerInit();
        }
    }

    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canEdit($this->getRecord()), 403);
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Actions\Action::make('pdf_program_with_times')
                    ->label('Program (z godzinami)')
                    ->url(fn () => route('admin.events.pdf', ['event' => $this->record->id, 'audience' => 'program_with_times']))
                    ->openUrlInNewTab(),
                Actions\Action::make('pdf_program_without_times')
                    ->label('Program (bez godzin)')
                    ->url(fn () => route('admin.events.pdf', ['event' => $this->record->id, 'audience' => 'program_without_times']))
                    ->openUrlInNewTab(),
            ])
                ->label('Program PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->button(),
        ];
    }

    /**
     * @return array<class-string<RelationManager>>
     */
    protected function getAllRelationManagers(): array
    {
        return [
            ProgramPointsRelationManager::make([
                'ownerProgramView' => $this->programView,
                'ownerProgramDay' => $this->programDay,
                'ownerProgramFilter' => $this->programFilter,
            ]),
        ];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return false;
    }

    public function setProgramView(string $view): void
    {
        if ($view === 'tree') {
            $view = 'days';
        }

        if (! in_array($view, ['list', 'planner', 'days'], true)) {
            return;
        }

        $this->programView = $view;

        if ($view === 'days') {
            $this->clampProgramDay();
        }

        if ($view === 'planner') {
            $this->dispatchPlannerInit();
        }
    }

    protected function dispatchPlannerInit(): void
    {
        $this->js('setTimeout(() => window.dispatchEvent(new CustomEvent("event-program-planner-init")), 250)');
    }

    public function setProgramFilter(string $filter): void
    {
        if (! in_array($filter, ['program', 'all'], true)) {
            return;
        }

        $this->programFilter = $filter;
    }

    public function updatedProgramFilter(): void
    {
        if (! in_array($this->programFilter, ['program', 'all'], true)) {
            $this->programFilter = 'all';
        }
    }

    public function setProgramDay(int $day): void
    {
        $this->programDay = $day;
        $this->clampProgramDay();
        $this->syncProgramDayStartTimeProperty();
        $this->syncProgramDayRouteProperty();
    }

    public function updateProgramDayStartTime(?string $time = null): void
    {
        $time = $time ?? $this->programDayStartTime;

        if (! preg_match('/^\d{2}:\d{2}$/', $time)) {
            $this->syncProgramDayStartTimeProperty();

            return;
        }

        $this->programDayStartTime = $time;

        /** @var Event $event */
        $event = $this->getRecord();
        $event->setProgramDayStartTime($this->programDay, $time);
        $event->save();

        $updated = app(EventProgramScheduleService::class)->relayoutDay(
            $event->fresh(),
            $this->programDay,
            onlyUnlocked: true,
            dayStart: $time,
            includeTimedNonProgramPoints: true,
        );

        $this->record = $event->fresh();

        Notification::make()
            ->title('Godzina startu dnia zapisana')
            ->body($updated > 0
                ? "Przeliczono {$updated} pozycji programu dla dnia {$this->programDay}."
                : 'Brak punktów do przeliczenia (sprawdź ręczne blokady godzin).')
            ->success()
            ->send();

        $this->dispatch('event-program-points-refresh');
        $this->dispatchPlannerInit();
    }

    public function updateProgramDayRoute(?string $route = null): void
    {
        /** @var Event $event */
        $event = $this->getRecord();

        // Slot fakultatywny = opcje pod stronę/szablon, nie dzień wycieczki z trasą.
        if ($event->isFacultativeProgramDay($this->programDay)) {
            return;
        }

        $route = trim($route ?? $this->programDayRoute);

        $event->setProgramDayRoute($this->programDay, $route !== '' ? $route : null);
        $event->save();

        $this->record = $event->fresh();
        $this->syncProgramDayRouteProperty();

        Notification::make()
            ->title('Trasa dnia zapisana')
            ->success()
            ->send();
    }

    protected function syncProgramDayRouteProperty(): void
    {
        /** @var Event $event */
        $event = $this->getRecord();
        $this->programDayRoute = $event->programDayRoute($this->programDay) ?? '';
    }

    /**
     * @return array<string, string>
     */
    public function getProgramDayStartTimeOptions(): array
    {
        return ProgramTimeSlots::options(15);
    }

    protected function syncProgramDayStartTimeProperty(): void
    {
        /** @var Event $event */
        $event = $this->getRecord();
        $this->programDayStartTime = $event->programDayStartTimeLabel($this->programDay);
    }

    /**
     * @return list<array{day: int, label: string, date: ?string, count: int, start_time: string}>
     */
    public function getProgramDayTabs(): array
    {
        /** @var Event $event */
        $event = $this->getRecord();
        $maxDay = $event->resolveProgramDaysCount();

        $counts = $this->filteredProgramPointsQuery()
            ->selectRaw('day, count(*) as aggregate')
            ->groupBy('day')
            ->pluck('aggregate', 'day');

        $tabs = [];

        for ($day = 1; $day <= $maxDay; $day++) {
            $tabs[] = [
                'day' => $day,
                'label' => $event->programDayLabel($day),
                'date' => $event->isFacultativeProgramDay($day)
                    ? null
                    : $event->dateForProgramDay($day)?->format('d.m.Y'),
                'count' => (int) ($counts[$day] ?? 0),
                'start_time' => $event->programDayStartTimeLabel($day),
                'route' => $event->programDayRoute($day),
            ];
        }

        return $tabs;
    }

    public function getProgramFilterCounts(): array
    {
        /** @var Event $event */
        $event = $this->getRecord();

        $all = (int) $event->programPoints()
            ->whereNull('parent_id')
            ->where('active', true)
            ->count();
        $program = (int) $event->programPoints()
            ->whereNull('parent_id')
            ->where('include_in_program', true)
            ->where('active', true)
            ->count();

        return [
            'program' => $program,
            'all' => $all,
        ];
    }

    protected function filteredProgramPointsQuery(): \Illuminate\Database\Eloquent\Builder
    {
        /** @var Event $event */
        $event = $this->getRecord();

        $query = EventProgramPoint::query()
            ->where('event_id', $event->getKey())
            ->whereNull('parent_id')
            ->where('active', true);

        if ($this->programFilter === 'program') {
            $query->where('include_in_program', true);
        }

        return $query;
    }

    protected function clampProgramDay(): void
    {
        $event = $this->getRecord();
        $maxDay = $event->resolveProgramDaysCount();
        $this->programDay = max(1, min($maxDay, (int) $this->programDay));
    }

    protected function dispatchProgramContextChanged(): void
    {
        $this->dispatch(
            'event-program-context-changed',
            programView: $this->programView,
            programDay: $this->programDay,
            programFilter: $this->programFilter,
        )->to(ProgramPointsRelationManager::class);
    }

    #[On('event-program-context-changed')]
    public function syncProgramContextFromRelationManager(?string $programView = null, ?int $programDay = null, ?string $programFilter = null): void
    {
        if ($programView !== null && in_array($programView, ['list', 'planner', 'days'], true)) {
            $this->programView = $programView;
        }

        if ($programDay !== null) {
            $this->programDay = $programDay;
            $this->clampProgramDay();
            $this->syncProgramDayStartTimeProperty();
            $this->syncProgramDayRouteProperty();
        }

        if ($programFilter !== null && in_array($programFilter, ['program', 'all'], true)) {
            $this->programFilter = $programFilter;
        }
    }

    public function updatedProgramDay(): void
    {
        $this->clampProgramDay();
        $this->syncProgramDayStartTimeProperty();
        $this->syncProgramDayRouteProperty();
    }

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::Full;
    }
}
