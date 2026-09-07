<?php

namespace App\Filament\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * Uprawnienia stron workflow szablonu imprezy (podgląd vs edycja).
 */
trait AuthorizesEventTemplatePages
{
    public static function canAccess(array $parameters = []): bool
    {
        if (! Auth::check()) {
            return false;
        }

        if (static::userCanViewEventTemplate()) {
            return true;
        }

        if (! static::requiresFullTemplateEdit()) {
            return static::userCanEditEventTemplateProgram();
        }

        return static::userCanEditEventTemplate();
    }

    protected static function requiresFullTemplateEdit(): bool
    {
        return true;
    }

    protected static function userCanEditEventTemplate(): bool
    {
        $user = Auth::user();

        return $user && (
            $user->hasRole(['admin', 'super_admin'])
            || $user->can('edit event_template')
        );
    }

    /**
     * Kto może zapisywać po świadomym odblokowaniu edycji (admin od razu, biuro po potwierdzeniu).
     */
    protected static function userCanMutateEventTemplate(): bool
    {
        $user = Auth::user();

        return $user && (
            $user->hasRole(['admin', 'super_admin', 'biuro'])
            || $user->can('edit event_template')
        );
    }

    protected static function userCanEditEventTemplateProgram(): bool
    {
        $user = Auth::user();

        return $user && (
            $user->hasRole(['admin', 'super_admin', 'biuro'])
            || $user->can('edit event_template')
            || $user->can('edit event_template_program')
        );
    }

    protected static function userCanViewEventTemplate(): bool
    {
        $user = Auth::user();

        return $user && (
            $user->hasRole(['admin', 'super_admin', 'biuro'])
            || $user->can('view event_template')
            || $user->can('edit event_template')
            || $user->can('edit event_template_program')
        );
    }
}
