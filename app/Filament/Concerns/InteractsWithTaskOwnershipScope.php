<?php

namespace App\Filament\Concerns;

use App\Support\Tasks\TaskQueryFilters;
use App\Support\UserUiPreferences;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Szybkie filtry zadań współdzielone przez listę, kanban, imprezę i kalendarz.
 * Układ zapisywany per użytkownik (users.ui_preferences) oraz w sesji jako cache.
 */
trait InteractsWithTaskOwnershipScope
{
    /** Moje (jak topbar) — domyślnie na liście/kanbanie. */
    public string $tasksScope = 'assigned';

    public bool $tasksOnlyUrgent = false;

    public bool $showFinishedTasks = false;

    /** overdue | today | this_week | has_due_date | no_due_date | '' */
    public string $dueFilter = '';

    /** office | system | all */
    public string $sourceFilter = 'all';

    public function setTasksScope(string $scope): void
    {
        if (! in_array($scope, TaskQueryFilters::OWNERSHIP_SCOPES, true)) {
            return;
        }

        $this->tasksScope = $scope;
        $this->afterTaskQuickFiltersChanged();
    }

    public function setDueFilter(string $filter): void
    {
        if ($filter === $this->dueFilter) {
            $this->dueFilter = '';
        } elseif (in_array($filter, TaskQueryFilters::DUE_FILTERS, true)) {
            $this->dueFilter = $filter;
        } else {
            return;
        }

        $this->afterTaskQuickFiltersChanged();
    }

    public function setSourceFilter(string $source): void
    {
        if (! in_array($source, TaskQueryFilters::SOURCE_FILTERS, true)) {
            return;
        }

        $this->sourceFilter = $source;
        $this->afterTaskQuickFiltersChanged();
    }

    public function resetTaskQuickFilters(): void
    {
        $this->tasksScope = $this->defaultTasksScope();
        $this->tasksOnlyUrgent = false;
        $this->showFinishedTasks = $this->defaultShowFinishedTasks();
        $this->dueFilter = '';
        $this->sourceFilter = $this->defaultSourceFilter();
        $this->afterTaskQuickFiltersChanged();
    }

    public function hasActiveTaskQuickFilters(): bool
    {
        return $this->tasksScope !== $this->defaultTasksScope()
            || $this->tasksOnlyUrgent
            || $this->showFinishedTasks !== $this->defaultShowFinishedTasks()
            || $this->dueFilter !== ''
            || $this->sourceFilter !== $this->defaultSourceFilter();
    }

    public function updatedTasksOnlyUrgent(): void
    {
        $this->afterTaskQuickFiltersChanged();
    }

    public function updatedShowFinishedTasks(): void
    {
        $this->afterTaskQuickFiltersChanged();
    }

    protected function defaultTasksScope(): string
    {
        return 'assigned';
    }

    protected function defaultShowFinishedTasks(): bool
    {
        return false;
    }

    protected function defaultSourceFilter(): string
    {
        return 'all';
    }

    protected function applyTasksScopeTo(Builder $query): Builder
    {
        return TaskQueryFilters::applyOwnershipScope($query, $this->tasksScope, Auth::id());
    }

    protected function applyTaskQuickFiltersTo(
        Builder $query,
        bool $applyFinished = true,
        bool $applySource = true,
    ): Builder {
        return TaskQueryFilters::applyQuickFilters(
            $query,
            $this->tasksScope,
            $this->tasksOnlyUrgent,
            $this->dueFilter,
            $applySource ? $this->sourceFilter : 'all',
            $applyFinished ? $this->showFinishedTasks : true,
            Auth::id(),
        );
    }

    /**
     * Hook dla stron (reset tabeli / invalidacja computed).
     * Kalendarz nadpisuje i persistuje też typy wpisów.
     */
    protected function afterTasksScopeChanged(): void
    {
        //
    }

    protected function afterTaskQuickFiltersChanged(): void
    {
        $this->persistTaskQuickFilters();
        $this->afterTasksScopeChanged();
    }

    protected function taskQuickFiltersSessionKey(): ?string
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function taskQuickFiltersState(): array
    {
        return [
            'tasksScope' => $this->tasksScope,
            'tasksOnlyUrgent' => $this->tasksOnlyUrgent,
            'showFinishedTasks' => $this->showFinishedTasks,
            'dueFilter' => $this->dueFilter,
            'sourceFilter' => $this->sourceFilter,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraTaskQuickFiltersState(): array
    {
        return [];
    }

    /**
     * Klucz w users.ui_preferences — bez id użytkownika (zapis jest na koncie).
     */
    protected function taskQuickFiltersPreferenceKey(): ?string
    {
        return $this->taskQuickFiltersSessionKey();
    }

    /**
     * @return array<string, mixed>
     */
    protected function loadStoredTaskQuickFilters(): array
    {
        $prefKey = $this->taskQuickFiltersPreferenceKey();
        $fromUser = $prefKey ? UserUiPreferences::get($prefKey) : [];

        $sessionKey = $this->taskQuickFiltersSessionKey();
        $fromSession = $sessionKey ? session($sessionKey, []) : [];
        $fromSession = is_array($fromSession) ? $fromSession : [];

        // Preferencje użytkownika są źródłem prawdy (przeżywają wylogowanie).
        // Sesja nadpisuje tylko gdy ma nowsze wartości z bieżącego logowania.
        return array_merge($fromUser, $fromSession);
    }

    protected function restoreTaskQuickFiltersFromSession(): void
    {
        $saved = $this->loadStoredTaskQuickFilters();

        if ($saved === []) {
            return;
        }

        $this->applyRestoredTaskQuickFilters($saved);
    }

    /**
     * @param  array<string, mixed>  $saved
     */
    protected function applyRestoredTaskQuickFilters(array $saved): void
    {
        if (isset($saved['tasksScope']) && is_string($saved['tasksScope'])
            && in_array($saved['tasksScope'], TaskQueryFilters::OWNERSHIP_SCOPES, true)) {
            $this->tasksScope = $saved['tasksScope'];
        }

        if (array_key_exists('tasksOnlyUrgent', $saved)) {
            $this->tasksOnlyUrgent = (bool) $saved['tasksOnlyUrgent'];
        }

        if (array_key_exists('showFinishedTasks', $saved)) {
            $this->showFinishedTasks = (bool) $saved['showFinishedTasks'];
        }

        if (isset($saved['dueFilter']) && is_string($saved['dueFilter'])
            && ($saved['dueFilter'] === '' || in_array($saved['dueFilter'], TaskQueryFilters::DUE_FILTERS, true))) {
            $this->dueFilter = $saved['dueFilter'];
        }

        if (isset($saved['sourceFilter']) && is_string($saved['sourceFilter'])
            && in_array($saved['sourceFilter'], TaskQueryFilters::SOURCE_FILTERS, true)) {
            $this->sourceFilter = $saved['sourceFilter'];
        }

        $this->applyRestoredExtraTaskQuickFilters($saved);
    }

    /**
     * Hook na dodatkowe pola (np. listSort w split-view).
     *
     * @param  array<string, mixed>  $saved
     */
    protected function applyRestoredExtraTaskQuickFilters(array $saved): void
    {
        //
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function persistTaskQuickFilters(array $extra = []): void
    {
        $state = array_merge($this->taskQuickFiltersState(), $this->extraTaskQuickFiltersState(), $extra);

        $sessionKey = $this->taskQuickFiltersSessionKey();

        if ($sessionKey) {
            $previous = session($sessionKey, []);
            session([$sessionKey => array_merge(is_array($previous) ? $previous : [], $state)]);
        }

        $prefKey = $this->taskQuickFiltersPreferenceKey();

        if ($prefKey) {
            UserUiPreferences::put($prefKey, $state);
        }
    }
}
