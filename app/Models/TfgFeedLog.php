<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TfgFeedLog extends Model
{
    protected $fillable = [
        'feed_identifier',
        'operation_type',
        'contracts_count',
        'sync_status',
        'sync_errors',
        'async_status',
        'async_errors',
        'payload_path',
        'payload_hash',
        'payload_version',
        'poll_attempts',
        'submitted_at',
        'completed_at',
    ];

    protected $casts = [
        'sync_errors' => 'array',
        'async_errors' => 'array',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function contracts(): BelongsToMany
    {
        return $this->belongsToMany(Contract::class, 'tfg_feed_log_contract')
            ->withPivot(['operation', 'correction_reason'])
            ->withTimestamps();
    }

    public function isProcessing(): bool
    {
        return in_array($this->async_status, ['PROCESSING', 'PENDING'], true);
    }

    public function isCompleted(): bool
    {
        return $this->async_status === 'COMPLETED';
    }

    public function isFailed(): bool
    {
        return in_array($this->async_status, ['FAILED', 'ERROR'], true);
    }
}
