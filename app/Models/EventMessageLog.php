<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventMessageLog extends Model
{
    public const SEGMENT_ALL = 'all';

    public const SEGMENT_OVERDUE = 'overdue';

    public const SEGMENT_MISSING_CONSENTS = 'missing_consents';

    protected $fillable = [
        'event_id',
        'segment',
        'mail_template_key',
        'recipients_count',
        'sent_count',
        'failed_count',
        'created_by',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
