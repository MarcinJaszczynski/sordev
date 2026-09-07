<?php

namespace App\Support;

/**
 * Uprawnienia operacyjne roli biuro (imprezy, szablony, kontrahenci, koszty — bez P&L).
 */
final class OfficeRolePermissions
{
    /** @return list<string> */
    public static function names(): array
    {
        return [
            'view user',
            'edit user',
            'create user',
            'view contractor',
            'edit contractor',
            'create contractor',
            'delete contractor',
            'view contact',
            'edit contact',
            'create contact',
            'delete contact',
            'view event_template',
            'edit event_template',
            'create event_template',
            'delete event_template',
            'view event_template_qty',
            'edit event_template_qty',
            'create event_template_qty',
            'delete event_template_qty',
            'view kategoria_szablonu',
            'edit kategoria_szablonu',
            'create kategoria_szablonu',
            'delete kategoria_szablonu',
            'view tag',
            'edit tag',
            'create tag',
            'delete tag',
            'view event',
            'create event',
            'edit event',
            'view transport_cost',
            'edit transport_cost',
            'create transport_cost',
            'delete transport_cost',
            'view markup',
            'edit markup',
            'create markup',
            'delete markup',
            'view currency',
            'view task',
            'edit task',
            'create task',
            'delete task',
            'view todo_status',
            'edit todo_status',
            'create todo_status',
            'delete todo_status',
        ];
    }
}
