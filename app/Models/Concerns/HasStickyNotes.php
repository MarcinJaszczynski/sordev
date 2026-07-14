<?php

namespace App\Models\Concerns;

use App\Models\StickyNote;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasStickyNotes
{
    public function stickyNotes(): MorphMany
    {
        return $this->morphMany(StickyNote::class, 'notable');
    }
}
