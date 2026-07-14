<?php

namespace App\Filament\Concerns;

trait AuthorizesVendorInvoices
{
    protected static function userCan(string $permission): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->hasRole(['admin', 'super_admin'])) {
            return true;
        }

        return $user->can($permission);
    }

    public static function canViewInvoices(): bool
    {
        return static::userCan('view_vendor_invoice');
    }

    public static function canImportInvoices(): bool
    {
        return static::userCan('import_vendor_invoice');
    }

    public static function canApproveInvoices(): bool
    {
        return static::userCan('approve_vendor_invoice');
    }

    public static function canManageAssignment(): bool
    {
        return static::userCan('manage_vendor_invoice_assignment');
    }
}
