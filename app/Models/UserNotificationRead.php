<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotificationRead extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'fingerprint',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function fingerprintFor(array $item): string
    {
        $type = (string) ($item['type'] ?? 'unknown');
        $id = (string) ($item['id'] ?? '');
        $revision = (string) ($item['revision'] ?? '');

        return hash('sha256', $type.'|'.$id.'|'.$revision);
    }
}
