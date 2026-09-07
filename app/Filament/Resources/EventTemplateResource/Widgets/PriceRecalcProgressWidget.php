<?php

namespace App\Filament\Resources\EventTemplateResource\Widgets;

use App\Services\PriceRecalcProgress;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class PriceRecalcProgressWidget extends Widget
{
    protected static string $view = 'filament.resources.event-template-resource.widgets.price-recalc-progress-widget';

    protected static bool $isLazy = true;

    public $progress = [];

    public $visible = false;

    public $dismissed = false;

    public $lastStartedAt = null;

    protected $listeners = [
        'priceRecalcStarted' => 'refreshProgress',
    ];

    protected int|string|array $columnSpan = 1;

    /** Poll tylko gdy trwa przeliczanie — bez zbędnych requestów Livewire na liście szablonów. */
    protected function getPollingInterval(): ?string
    {
        if ($this->dismissed) {
            return null;
        }

        $total = $this->progress['total'] ?? 0;
        $finished = $this->progress['finished'] ?? false;

        return ($total > 0 && ! $finished) ? '5s' : null;
    }

    public function mount()
    {
        $this->refreshProgress();
    }

    public function refreshProgress(): void
    {
        $userId = Auth::id() ?: 0;
        if ($userId) {
            $this->progress = PriceRecalcProgress::get($userId);
        } else {
            $this->progress = ['total' => 0, 'processed' => 0, 'errors' => 0, 'finished' => false];
        }

        $startedAt = $this->progress['started_at'] ?? null;
        if ($startedAt && $this->lastStartedAt !== $startedAt) {
            $this->dismissed = false;
            $this->lastStartedAt = $startedAt;
        }

        $total = $this->progress['total'] ?? 0;
        $finished = $this->progress['finished'] ?? false;
        // pokaż toast dopóki trwa przeliczanie
        $this->visible = ! $this->dismissed && $total > 0 && ! $finished;
    }

    public function closeToast(): void
    {
        $this->dismissed = true;
        $this->visible = false;
    }

    public function getHeader(): ?string
    {
        return 'Postęp przeliczania cen';
    }
}
