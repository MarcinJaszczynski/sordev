<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailTemplate extends Model
{
    public const KEY_PAYMENT_REMINDER = 'payment_reminder';

    public const KEY_STATUS_CHANGED = 'event_status_changed';

    public const KEY_EVENT_BULK_NOTICE = 'event_bulk_notice';

    protected $fillable = [
        'key',
        'name',
        'subject',
        'body_html',
        'placeholders',
        'is_active',
    ];

    protected $casts = [
        'placeholders' => 'array',
        'is_active' => 'boolean',
    ];
}
