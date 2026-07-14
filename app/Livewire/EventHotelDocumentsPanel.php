<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\VendorInvoice;
use App\Services\Invoices\ContractorResolver;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class EventHotelDocumentsPanel extends Component
{
    public int $eventId;

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
    }

    public function render()
    {
        $event = Event::query()
            ->with(['hotelStays.contractor'])
            ->findOrFail($this->eventId);

        $contractorIds = $event->hotelStays
            ->pluck('contractor_id')
            ->filter()
            ->unique()
            ->values();

        $documentsUrl = \App\Filament\Resources\EventResource::getUrl('documents', ['record' => $event->id]);

        $invoices = collect();

        if ($contractorIds->isNotEmpty() && class_exists(VendorInvoice::class)) {
            $invoices = VendorInvoice::query()
                ->with(['contractor', 'event'])
                ->where('event_id', $event->id)
                ->where(function ($query) use ($contractorIds, $event): void {
                    $query->whereIn('contractor_id', $contractorIds);

                    $nips = $event->hotelStays
                        ->map(fn ($stay) => ContractorResolver::normalizeNip($stay->contractor?->nip))
                        ->filter()
                        ->unique()
                        ->values();

                    foreach ($nips as $nip) {
                        $query->orWhere('seller_nip', $nip);
                    }
                })
                ->orderByDesc('issue_date')
                ->limit(20)
                ->get();
        }

        $eventDocuments = Schema::hasTable('event_documents')
            ? $event->documents()->orderByDesc('updated_at')->limit(10)->get()
            : collect();

        return view('livewire.event-hotel-documents-panel', [
            'event' => $event,
            'contractors' => $event->hotelStays->pluck('contractor')->filter()->unique('id')->values(),
            'invoices' => $invoices,
            'eventDocuments' => $eventDocuments,
            'documentsUrl' => $documentsUrl,
        ]);
    }
}
