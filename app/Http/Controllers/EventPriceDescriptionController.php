<?php

namespace App\Http\Controllers;

use App\Models\EventPriceDescription;
use Illuminate\Http\Request;

class EventPriceDescriptionController extends Controller
{
    public function show($eventId)
    {
        $desc = EventPriceDescription::where('event_id', $eventId)->first();
        return view('event-price-description', [
            'description' => $desc ? $desc->description : '<p>Brak opisu.</p>',
        ]);
    }

    public function edit($eventId)
    {
        $desc = EventPriceDescription::firstOrNew(['event_id' => $eventId]);
        return view('event-price-description-edit', [
            'description' => $desc->description,
            'eventId' => $eventId,
        ]);
    }

    public function update(Request $request, $eventId)
    {
        $request->validate([
            'description' => 'required|string',
        ]);
        $desc = EventPriceDescription::updateOrCreate(
            ['event_id' => $eventId],
            ['description' => $request->input('description')]
        );
        return redirect()->route('event.price-description.show', $eventId);
    }
}
