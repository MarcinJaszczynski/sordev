<?php

namespace App\Filament\Concerns;

use App\Support\Tasks\TaskQueryFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait InteractsWithTaskOwnershipScope
{
    public string $tasksScope = 'assigned';

    public function setTasksScope(string $scope): void
    {
        if (! in_array($scope, ['assigned', 'authored', 'all'], true)) {
            return;
        }

        $this->tasksScope = $scope;
        $this->afterTasksScopeChanged();
    }

    protected function applyTasksScopeTo(Builder $query): Builder
    {
        return TaskQueryFilters::applyOwnershipScope($query, $this->tasksScope, Auth::id());
    }

    protected function afterTasksScopeChanged(): void
    {
        //
    }
}
