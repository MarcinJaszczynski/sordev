<?php

namespace App\Filament\Concerns;

trait DispatchesTopbarNotificationRefresh
{
    protected function dispatchTopbarNotificationRefresh(): void
    {
        $this->dispatch('refresh-notifications');
        $this->js('window.dispatchEvent(new CustomEvent("refresh-notifications"))');
    }
}
