<?php

namespace App\Support\Tasks;

use App\Models\Contractor;
use App\Models\Event;
use App\Models\EventDocument;
use App\Models\EventProgramPoint;
use App\Models\EventSettlementCost;
use App\Models\EventSettlementDocument;
use App\Models\EventSettlementParticipantPayment;
use App\Models\EventTemplate;
use App\Models\EventTemplateProgramPoint;
use App\Models\PilotCashPreparation;
use App\Models\Reservation;
use App\Models\Task;
use App\Support\Calendar\CalendarEventLinks;
use Illuminate\Database\Eloquent\Model;

class TaskContextRegistry
{
    public static function types(): array
    {
        return [
            Event::class => 'Impreza',
            EventTemplate::class => 'Szablon imprezy',
            EventProgramPoint::class => 'Punkt programu imprezy',
            EventTemplateProgramPoint::class => 'Punkt programu szablonu',
            Contractor::class => 'Kontrahent',
            EventDocument::class => 'Dokument imprezy',
            EventSettlementCost::class => 'Pozycja kosztu rozliczenia',
            EventSettlementDocument::class => 'Dokument rozliczenia',
            EventSettlementParticipantPayment::class => 'Wpłata uczestnika',
            Reservation::class => 'Rezerwacja u dostawcy',
            PilotCashPreparation::class => 'Gotówka pilota',
        ];
    }

    public static function supportedTypes(): array
    {
        return array_keys(static::types());
    }

    public static function isSupported(?string $type): bool
    {
        return filled($type) && array_key_exists($type, static::types());
    }

    public static function labelForType(?string $type): ?string
    {
        return static::types()[$type] ?? null;
    }

    public static function recordOptions(?string $type): array
    {
        if (! static::isSupported($type)) {
            return [];
        }

        return match ($type) {
            Event::class => Event::query()
                ->orderByDesc('start_date')
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (Event $event) => [$event->getKey() => static::labelForRecord($event)])
                ->all(),

            EventTemplate::class => EventTemplate::query()
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (EventTemplate $template) => [$template->getKey() => static::labelForRecord($template)])
                ->all(),

            EventProgramPoint::class => EventProgramPoint::query()
                ->with(['event:id,name,start_date', 'templatePoint:id,name'])
                ->orderBy('event_id')
                ->orderBy('day')
                ->orderBy('order')
                ->get()
                ->mapWithKeys(fn (EventProgramPoint $programPoint) => [$programPoint->getKey() => static::labelForRecord($programPoint)])
                ->all(),

            EventTemplateProgramPoint::class => EventTemplateProgramPoint::query()
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (EventTemplateProgramPoint $programPoint) => [$programPoint->getKey() => static::labelForRecord($programPoint)])
                ->all(),

            Contractor::class => Contractor::query()
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (Contractor $contractor) => [$contractor->getKey() => static::labelForRecord($contractor)])
                ->all(),

            EventDocument::class => EventDocument::query()
                ->with(['event:id,name,start_date'])
                ->orderByDesc('id')
                ->limit(1000)
                ->get()
                ->mapWithKeys(fn (EventDocument $doc) => [$doc->getKey() => static::labelForRecord($doc)])
                ->all(),

            EventSettlementCost::class => EventSettlementCost::query()
                ->with(['settlement.event:id,name,start_date'])
                ->orderByDesc('id')
                ->limit(1000)
                ->get()
                ->mapWithKeys(fn (EventSettlementCost $cost) => [$cost->getKey() => static::labelForRecord($cost)])
                ->all(),

            EventSettlementDocument::class => EventSettlementDocument::query()
                ->with(['settlement.event:id,name,start_date'])
                ->orderByDesc('id')
                ->limit(1000)
                ->get()
                ->mapWithKeys(fn (EventSettlementDocument $doc) => [$doc->getKey() => static::labelForRecord($doc)])
                ->all(),

            EventSettlementParticipantPayment::class => EventSettlementParticipantPayment::query()
                ->with(['settlement.event:id,name,start_date'])
                ->orderByDesc('id')
                ->limit(1000)
                ->get()
                ->mapWithKeys(fn (EventSettlementParticipantPayment $payment) => [$payment->getKey() => static::labelForRecord($payment)])
                ->all(),

            Reservation::class => Reservation::query()
                ->with([
                    'event:id,name,start_date',
                    'contractor:id,name',
                    'programPoint:id,name,event_id',
                ])
                ->orderByDesc('id')
                ->limit(1000)
                ->get()
                ->mapWithKeys(fn (Reservation $reservation) => [$reservation->getKey() => static::labelForRecord($reservation)])
                ->all(),

            PilotCashPreparation::class => PilotCashPreparation::query()
                ->with(['settlement.event:id,name,start_date', 'currency:id,name,symbol'])
                ->orderByDesc('id')
                ->limit(1000)
                ->get()
                ->mapWithKeys(fn (PilotCashPreparation $cash) => [$cash->getKey() => static::labelForRecord($cash)])
                ->all(),
        };
    }

    public static function labelForRecord(?Model $record): ?string
    {
        if (! $record) {
            return null;
        }

        return match (true) {
            $record instanceof Event => static::formatEventLabel($record),
            $record instanceof EventTemplate => static::formatEventTemplateLabel($record),
            $record instanceof EventProgramPoint => static::formatEventProgramPointLabel($record),
            $record instanceof EventTemplateProgramPoint => static::formatEventTemplateProgramPointLabel($record),
            $record instanceof Contractor => static::formatContractorLabel($record),
            $record instanceof EventDocument => static::formatEventDocumentLabel($record),
            $record instanceof EventSettlementCost => static::formatSettlementCostLabel($record),
            $record instanceof EventSettlementDocument => static::formatSettlementDocumentLabel($record),
            $record instanceof EventSettlementParticipantPayment => static::formatSettlementPaymentLabel($record),
            $record instanceof Reservation => static::formatReservationLabel($record),
            $record instanceof PilotCashPreparation => static::formatPilotCashLabel($record),
            default => method_exists($record, '__toString') ? (string) $record : class_basename($record).' #'.$record->getKey(),
        };
    }

    protected static function formatEventLabel(Event $event): string
    {
        $date = $event->start_date?->format('d.m.Y');

        return trim(sprintf('%s%s (#%d)', $event->name, $date ? ' • '.$date : '', $event->getKey()));
    }

    protected static function formatEventTemplateLabel(EventTemplate $template): string
    {
        return sprintf('%s (#%d)', $template->name, $template->getKey());
    }

    protected static function formatEventProgramPointLabel(EventProgramPoint $programPoint): string
    {
        $pointName = $programPoint->name
            ?: ($programPoint->relationLoaded('templatePoint') ? $programPoint->templatePoint?->name : null)
            ?: 'Punkt programu';

        $eventName = $programPoint->relationLoaded('event') ? $programPoint->event?->name : null;
        $dayLabel = $programPoint->day ? ' • dzień '.$programPoint->day : '';

        return trim($pointName.($eventName ? ' • '.$eventName : '').$dayLabel.' (#'.$programPoint->getKey().')');
    }

    protected static function formatEventTemplateProgramPointLabel(EventTemplateProgramPoint $programPoint): string
    {
        return sprintf('%s (#%d)', $programPoint->name ?: 'Punkt programu szablonu', $programPoint->getKey());
    }

    protected static function formatContractorLabel(Contractor $contractor): string
    {
        return sprintf('%s (#%d)', $contractor->name, $contractor->getKey());
    }

    protected static function formatEventDocumentLabel(EventDocument $document): string
    {
        $eventName = $document->relationLoaded('event')
            ? $document->event?->name
            : $document->event?->name;

        $name = $document->name ?: ('Dokument #'.$document->getKey());

        return trim($name.($eventName ? ' • '.$eventName : ''));
    }

    protected static function formatSettlementCostLabel(EventSettlementCost $cost): string
    {
        $eventName = $cost->relationLoaded('settlement')
            ? $cost->settlement?->event?->name
            : $cost->settlement?->event?->name;

        return trim(($cost->name ?: 'Pozycja kosztu').($eventName ? ' • '.$eventName : '').' (#'.$cost->getKey().')');
    }

    protected static function formatSettlementDocumentLabel(EventSettlementDocument $document): string
    {
        $eventName = $document->relationLoaded('settlement')
            ? $document->settlement?->event?->name
            : $document->settlement?->event?->name;

        $docNumber = $document->document_number ?: ('Dokument #'.$document->getKey());

        return trim($docNumber.($eventName ? ' • '.$eventName : ''));
    }

    protected static function formatSettlementPaymentLabel(EventSettlementParticipantPayment $payment): string
    {
        $eventName = $payment->relationLoaded('settlement')
            ? $payment->settlement?->event?->name
            : $payment->settlement?->event?->name;

        $label = $payment->participant_name ?: 'Wpłata uczestnika';

        return trim($label.($eventName ? ' • '.$eventName : '').' (#'.$payment->getKey().')');
    }

    protected static function formatReservationLabel(Reservation $reservation): string
    {
        $reference = $reservation->booking_reference
            ?: ('Rezerwacja #'.$reservation->getKey());

        $eventName = $reservation->relationLoaded('event')
            ? $reservation->event?->name
            : $reservation->event?->name;

        $pointName = null;
        if ($reservation->relationLoaded('programPoint') && $reservation->programPoint) {
            $pointName = $reservation->programPoint->name;
        }

        $contractorName = $reservation->relationLoaded('contractor')
            ? $reservation->contractor?->name
            : $reservation->contractor?->name;

        return trim(
            $reference
            .($eventName ? ' • '.$eventName : '')
            .($pointName ? ' • '.$pointName : '')
            .($contractorName && ! $pointName ? ' • '.$contractorName : '')
            .' (#'.$reservation->getKey().')'
        );
    }

    protected static function formatPilotCashLabel(PilotCashPreparation $cash): string
    {
        $eventName = $cash->relationLoaded('settlement')
            ? $cash->settlement?->event?->name
            : $cash->settlement?->event?->name;
        $currency = $cash->relationLoaded('currency') ? $cash->currency?->symbol : $cash->currency?->symbol;

        return trim('Gotówka pilota'.($currency ? ' '.$currency : '').($eventName ? ' • '.$eventName : '').' (#'.$cash->getKey().')');
    }

    public static function urlForRecord(?Model $record): ?string
    {
        $links = static::linksForRecord($record);

        return $links[0]['url'] ?? null;
    }

    /**
     * @return array<int, array{label: string, url: string, icon: string}>
     */
    public static function linksForTask(?Task $task): array
    {
        $contextTask = static::resolveEffectiveContextTask($task);

        if (! $contextTask) {
            return [];
        }

        $contextTask->loadMissing('taskable');

        return static::linksForRecord($contextTask->taskable);
    }

    public static function resolveEffectiveContextTask(?Task $task): ?Task
    {
        if (! $task) {
            return null;
        }

        $seen = [];
        $cursor = $task;

        while ($cursor) {
            if (isset($seen[$cursor->id])) {
                break;
            }

            $seen[$cursor->id] = true;

            if ($cursor->taskable_type && $cursor->taskable_id) {
                return $cursor;
            }

            if (! $cursor->parent_id) {
                break;
            }

            $cursor->loadMissing('parent');
            $cursor = $cursor->parent;
        }

        return null;
    }

    /**
     * @return array<int, array{label: string, url: string, icon: string}>
     */
    public static function linksForRecord(?Model $record): array
    {
        if (! $record) {
            return [];
        }

        try {
            return CalendarEventLinks::compact(match (true) {
                $record instanceof Event => [
                    CalendarEventLinks::event($record->getKey()),
                ],
                $record instanceof EventTemplate => [
                    CalendarEventLinks::link(
                        \App\Filament\Resources\EventTemplateResource::getUrl('edit', ['record' => $record]),
                        'Szablon imprezy',
                        'heroicon-o-document-duplicate',
                    ),
                ],
                $record instanceof Contractor => [
                    CalendarEventLinks::contractor($record->getKey()),
                ],
                $record instanceof EventProgramPoint => static::eventProgramPointLinks($record),
                $record instanceof EventTemplateProgramPoint => [
                    CalendarEventLinks::link(
                        $record->event_template_id
                            ? \App\Filament\Resources\EventTemplateResource::getUrl('edit', ['record' => $record->event_template_id])
                            : null,
                        'Szablon imprezy',
                        'heroicon-o-document-duplicate',
                    ),
                ],
                $record instanceof EventDocument => [
                    CalendarEventLinks::event($record->event_id),
                    CalendarEventLinks::eventDocuments($record->event_id),
                ],
                $record instanceof EventSettlementCost => static::settlementContextLinks(
                    $record->settlement_id,
                    \App\Filament\Resources\EventSettlementResource::getEventFinanceUrlForSettlement($record->settlement_id),
                    'Finanse imprezy',
                    'heroicon-o-receipt-percent',
                ),
                $record instanceof EventSettlementDocument => static::settlementContextLinks(
                    $record->settlement_id,
                    \App\Filament\Resources\EventSettlementResource::getEventFinanceUrlForSettlement($record->settlement_id),
                    'Finanse imprezy',
                    'heroicon-o-document',
                ),
                $record instanceof EventSettlementParticipantPayment => static::settlementContextLinks(
                    $record->settlement_id,
                    \App\Filament\Resources\EventSettlementResource::getEventFinanceUrlForSettlement($record->settlement_id),
                    'Finanse imprezy',
                    'heroicon-o-banknotes',
                ),
                $record instanceof Reservation => static::reservationLinks($record),
                $record instanceof PilotCashPreparation => static::settlementContextLinks(
                    $record->settlement_id,
                    \App\Filament\Resources\EventSettlementResource::getEventFinanceUrlForSettlement($record->settlement_id),
                    'Finanse imprezy',
                    'heroicon-o-wallet',
                ),
                default => [],
            });
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * @return array<int, array{label: string, url: string, icon?: string}|null>
     */
    protected static function reservationLinks(Reservation $reservation): array
    {
        $reservation->loadMissing([
            'event:id',
            'contractor:id',
            'programPoint:id,event_id,contractor_id',
            'settlementCost:id,settlement_id',
        ]);

        return [
            CalendarEventLinks::reservation($reservation->getKey()),
            CalendarEventLinks::event($reservation->event_id),
            CalendarEventLinks::eventProgram($reservation->event_id),
            CalendarEventLinks::eventReservations($reservation->event_id),
            CalendarEventLinks::contractor($reservation->contractor_id),
            $reservation->settlementCost?->settlement_id
                ? CalendarEventLinks::link(
                    \App\Filament\Resources\EventSettlementResource::getEventFinanceUrlForSettlement(
                        $reservation->settlementCost->settlement_id
                    ),
                    'Finanse imprezy',
                    'heroicon-o-receipt-percent',
                )
                : null,
        ];
    }

    /**
     * @return array<int, array{label: string, url: string, icon?: string}|null>
     */
    protected static function eventProgramPointLinks(EventProgramPoint $programPoint): array
    {
        $programPoint->loadMissing(['event:id', 'contractor:id']);

        return [
            CalendarEventLinks::event($programPoint->event_id),
            CalendarEventLinks::eventProgram($programPoint->event_id),
            CalendarEventLinks::contractor($programPoint->contractor_id),
        ];
    }

    /**
     * @return array<int, array{label: string, url: string, icon?: string}|null>
     */
    protected static function settlementContextLinks(?int $settlementId, ?string $detailUrl, string $detailLabel, string $detailIcon): array
    {
        if (! $settlementId) {
            return [];
        }

        $settlement = \App\Models\EventSettlement::query()
            ->select(['id', 'event_id'])
            ->find($settlementId);

        return [
            CalendarEventLinks::event($settlement?->event_id),
            CalendarEventLinks::settlement($settlementId),
            CalendarEventLinks::link($detailUrl, $detailLabel, $detailIcon),
        ];
    }
}
