<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Event;
use App\Models\EventProgramPoint;
use Illuminate\Support\Arr;

class ContractAnnexService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshotProgram(Event $event): array
    {
        $points = $event->programPoints()
            ->where('include_in_program', true)
            ->where('active', true)
            ->whereNull('parent_id')
            ->orderBy('day')
            ->orderBy('order')
            ->with(['children' => fn ($query) => $query
                ->where('include_in_program', true)
                ->where('active', true)
                ->orderBy('order'),
            ])
            ->get();

        return [
            'captured_at' => now()->toIso8601String(),
            'event_id' => $event->id,
            'event_name' => $event->name,
            'points' => $points->map(fn (EventProgramPoint $point): array => $this->serializeProgramPoint($point))->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function applyAnnexAttributes(Contract $annex, array $data, ?Event $event = null): void
    {
        $changeTypes = array_values(array_filter(Arr::wrap($data['annex_change_types'] ?? [])));
        $includesProgramChange = in_array(Contract::ANNEX_CHANGE_PROGRAM, $changeTypes, true);

        $snapshot = $annex->annex_program_snapshot;

        if ($includesProgramChange) {
            $event ??= $annex->event;

            if ($event) {
                $snapshot = $this->snapshotProgram($event);
            }
        } elseif (! $includesProgramChange) {
            $snapshot = null;
        }

        $bodyEditMode = (string) ($data['body_edit_mode'] ?? Contract::BODY_EDIT_TEMPLATE);

        $annex->forceFill([
            'annex_change_types' => $changeTypes !== [] ? $changeTypes : null,
            'annex_program_change_notes' => $includesProgramChange
                ? ($data['annex_program_change_notes'] ?? null)
                : null,
            'annex_program_snapshot' => $snapshot,
            'body_edit_mode' => $bodyEditMode,
            'agreement_body' => $bodyEditMode === Contract::BODY_EDIT_MANUAL
                ? ($data['agreement_body'] ?? $annex->agreement_body)
                : $annex->agreement_body,
        ])->saveQuietly();
    }

    /**
     * @param  array<int, string>  $changeTypes
     */
    public function formatChangeTypesLabels(array $changeTypes): string
    {
        return collect($changeTypes)
            ->map(fn (string $type): string => Contract::$annexChangeTypes[$type] ?? $type)
            ->implode(', ');
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     */
    public function formatProgramSnapshotText(?array $snapshot): string
    {
        if (blank($snapshot) || blank($snapshot['points'] ?? null)) {
            return '—';
        }

        return collect($snapshot['points'])
            ->map(function (array $point): string {
                $day = filled($point['day'] ?? null) ? 'Dzień '.$point['day'].': ' : '';
                $time = filled($point['start_time'] ?? null) ? ' ('.$point['start_time'].')' : '';
                $line = $day.$point['name'].$time;

                $children = collect($point['children'] ?? [])
                    ->map(fn (array $child): string => '  - '.$child['name'])
                    ->implode("\n");

                return $children !== '' ? $line."\n".$children : $line;
            })
            ->implode("\n");
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     */
    public function formatProgramSnapshotHtml(?array $snapshot): string
    {
        if (blank($snapshot) || blank($snapshot['points'] ?? null)) {
            return '<p>Brak zapisanego programu w aneksie.</p>';
        }

        $items = collect($snapshot['points'])
            ->map(function (array $point): string {
                $day = filled($point['day'] ?? null) ? '<strong>Dzień '.e((string) $point['day']).':</strong> ' : '';
                $time = filled($point['start_time'] ?? null) ? ' <span class="text-muted">('.e((string) $point['start_time']).')</span>' : '';
                $description = filled($point['description'] ?? null)
                    ? '<div class="small text-muted">'.e((string) $point['description']).'</div>'
                    : '';

                $children = collect($point['children'] ?? [])
                    ->map(fn (array $child): string => '<li>'.e((string) $child['name']).'</li>')
                    ->implode('');

                $childrenList = $children !== ''
                    ? '<ul class="mb-0 ps-3">'.$children.'</ul>'
                    : '';

                return '<li class="mb-2">'.$day.e((string) $point['name']).$time.$description.$childrenList.'</li>';
            })
            ->implode('');

        return '<ul class="list-unstyled mb-0">'.$items.'</ul>';
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeProgramPoint(EventProgramPoint $point): array
    {
        return [
            'id' => $point->id,
            'day' => $point->day,
            'order' => $point->order,
            'name' => $point->name,
            'description' => $point->description,
            'start_time' => $point->start_time,
            'end_time' => $point->end_time,
            'children' => $point->children
                ->map(fn (EventProgramPoint $child): array => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'description' => $child->description,
                    'start_time' => $child->start_time,
                    'end_time' => $child->end_time,
                ])
                ->values()
                ->all(),
        ];
    }
}
