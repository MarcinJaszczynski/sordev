<?php

return [
    'schedule_enabled' => env('BACKUP_SCHEDULE_ENABLED', true),

    'schedule_cron' => env('BACKUP_SCHEDULE_CRON', '0 2 * * *'),

    'retention_count' => (int) env('BACKUP_RETENTION_COUNT', 7),

    'scheduled_components' => env('BACKUP_SCHEDULED_COMPONENTS', 'db,storage'),
];
