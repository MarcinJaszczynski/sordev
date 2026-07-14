<?php

namespace App\Services\Tfg;

use App\Models\Contract;
use Illuminate\Support\Collection;

class TfgFeedBundler
{
    public function __construct(
        protected int $maxContracts = 0,
    ) {
        $this->maxContracts = $maxContracts ?: (int) config('tfg.feed.max_contracts', 1000);
    }

    /**
     * @return Collection<int, Collection<int, Contract>>
     */
    public function bundle(Collection $contracts): Collection
    {
        return $contracts->values()->chunk($this->maxContracts);
    }
}
