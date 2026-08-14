<?php

namespace App\Filament\Pilot\Pages;

use App\Filament\Pilot\Concerns\AuthorizesPilotTrip;
use App\Filament\Pilot\Concerns\HasPilotTripNav;
use App\Models\Event;
use App\Models\EventDocument;
use App\Models\EventSettlement;
use App\Models\EventSettlementDocument;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Hub dokumentów pilota — pakiet PDF + dokumenty udostępnione w pakiecie pilota.
 */
class PilotDocumentsPage extends Page
{
    use AuthorizesPilotTrip;
    use HasPilotTripNav;

    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-down';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pilot.pages.pilot-documents-page';

    protected static ?string $slug = 'documents/{event}';

    public Event $event;

    public function mount(Event $event): void
    {
        $this->authorizePilotTrip($event, requireFullAccess: true);

        $this->event = $event;
    }

    public function getPilotTripNavActiveTab(): ?string
    {
        return 'documents';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Dokumenty: '.$this->event->name;
    }

    public function getSubheading(): ?string
    {
        return 'Pakiet pilota i dokumenty udostępnione Ci przez biuro';
    }

    public static function urlFor(Event $event): string
    {
        return static::getUrl(['event' => $event->id], panel: 'pilot');
    }

    /**
     * @return Collection<int, EventDocument>
     */
    public function getSharedEventDocumentsProperty(): Collection
    {
        if (! Schema::hasTable('event_documents')) {
            return collect();
        }

        return EventDocument::query()
            ->where('event_id', $this->event->id)
            ->where('attach_to_pilot_pdf', true)
            ->when(
                Schema::hasColumn('event_documents', 'approval_status'),
                // „Pakiet pilota” = udostępnienie; kontrola biurowa blokuje tylko odrzucone.
                fn ($q) => $q->where(function ($inner) {
                    $inner->whereNull('approval_status')
                        ->orWhere('approval_status', '!=', 'rejected');
                }),
            )
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, EventSettlementDocument>
     */
    public function getSharedSettlementDocumentsProperty(): Collection
    {
        if (! Schema::hasTable('event_settlement_documents')
            || ! Schema::hasColumn('event_settlement_documents', 'settlement_id')
            || ! Schema::hasTable('event_settlements')) {
            return collect();
        }

        if (! Schema::hasColumn('event_settlement_documents', 'attach_to_pilot_pdf')) {
            return collect();
        }

        $settlementIds = EventSettlement::query()
            ->where('event_id', $this->event->id)
            ->pluck('id');

        if ($settlementIds->isEmpty()) {
            return collect();
        }

        return EventSettlementDocument::query()
            ->whereIn('settlement_id', $settlementIds)
            ->where('attach_to_pilot_pdf', true)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Polisa + oryginalna lista ubezpieczonych z Operacje → Ubezpieczenia.
     *
     * @return Collection<int, array{key: string, label: string, path: string, url: string}>
     */
    public function getInsuranceDocumentsProperty(): Collection
    {
        return collect($this->event->insuranceFilesForPilot())
            ->map(function (array $file): ?array {
                $path = $file['path'] ?? null;
                if (! filled($path) || ! Storage::disk('public')->exists((string) $path)) {
                    return null;
                }

                return [
                    'key' => (string) $file['key'],
                    'label' => (string) $file['label'],
                    'path' => (string) $path,
                    'url' => Storage::disk('public')->url((string) $path),
                ];
            })
            ->filter()
            ->values();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pilot_pdf')
                ->label('Teczka / pakiet pilota PDF')
                ->icon('heroicon-o-document-text')
                ->color('primary')
                ->url(route('pilot.events.pdf', ['event' => $this->event, 'audience' => 'pilot']))
                ->openUrlInNewTab(),
        ];
    }
}
