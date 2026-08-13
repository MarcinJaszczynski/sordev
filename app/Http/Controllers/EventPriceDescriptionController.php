<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventPriceDescription;
use App\Support\AgreementHtml;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EventPriceDescriptionController extends Controller
{
    public function show(Event $event)
    {
        Gate::authorize('view', $event);

        $desc = $this->resolveDescription($event);
        $raw = $desc ? (string) $desc->description : '<p>Brak opisu.</p>';

        return view('event-price-description', [
            'description' => AgreementHtml::sanitize($raw),
        ]);
    }

    public function edit(Event $event)
    {
        Gate::authorize('update', $event);

        $desc = $this->resolveDescription($event);

        return view('event-price-description-edit', [
            'description' => $desc?->description,
            'eventId' => $event->id,
        ]);
    }

    public function update(Request $request, Event $event)
    {
        Gate::authorize('update', $event);

        $request->validate([
            'description' => 'required|string',
        ]);

        $html = AgreementHtml::sanitize($request->input('description'));
        $desc = $this->resolveDescription($event);

        if ($desc) {
            $desc->update(['description' => $html]);
        } else {
            $desc = EventPriceDescription::query()->create([
                'name' => 'Opis ceny: '.(string) ($event->name ?: ('Event #'.$event->id)),
                'description' => $html,
            ]);

            $template = $event->eventTemplate;
            if ($template) {
                $template->eventPriceDescription()->sync([$desc->id]);
            }
        }

        return redirect()->route('event.price-description.show', $event);
    }

    private function resolveDescription(Event $event): ?EventPriceDescription
    {
        $event->loadMissing('eventTemplate');

        $template = $event->eventTemplate;
        if (! $template) {
            return null;
        }

        return $template->eventPriceDescription()->first();
    }
}
