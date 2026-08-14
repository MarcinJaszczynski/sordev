<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Crm\CreateInquiryFromWebAction;
use App\Data\CreateInquiryFromWebData;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\EventTemplate;
use App\Models\Place;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InquiryController extends BaseApiController
{
    public function store(Request $request, CreateInquiryFromWebAction $action): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'telephone' => ['required', 'string', 'max:50'],
            'message' => ['nullable', 'string', 'max:5000'],
            'package_id' => ['nullable', 'integer', 'min:1'],
            'package_slug' => ['nullable', 'string', 'max:255'],
            'start_place_id' => ['nullable', 'integer', 'min:1'],
            'website' => ['nullable', 'string', 'max:255'], // honeypot
        ]);

        if (filled($validated['website'] ?? null)) {
            return $this->success(['accepted' => true], 'OK');
        }

        $template = null;
        if (! empty($validated['package_id'])) {
            $template = EventTemplate::query()->active()->find($validated['package_id']);
        } elseif (! empty($validated['package_slug'])) {
            $template = EventTemplate::query()->active()->where('slug', $validated['package_slug'])->first();
        }

        $startPlaceName = null;
        if (! empty($validated['start_place_id'])) {
            $startPlaceName = Place::query()->find($validated['start_place_id'])?->name;
        }

        $result = $action(new CreateInquiryFromWebData(
            email: $validated['email'],
            telephone: $validated['telephone'],
            name: $validated['name'] ?? null,
            message: $validated['message'] ?? null,
            eventName: $template?->name,
            eventUrl: $template ? url('/api/v1/public/packages/'.$template->slug) : null,
            startPlaceName: $startPlaceName,
        ));

        return $this->success([
            'contact_id' => $result['contact']->id ?? null,
            'event_id' => $result['event']->id ?? null,
        ], 'Zapytanie przyjęte.', 201);
    }
}
