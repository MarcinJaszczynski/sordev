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
            $record instanceof PilotCashPreparation => static::formatPilotCashLabel($record),
            default => method_exists($record, '__toString') ? (string) $record : class_basename($record) . ' #' . $record->getKey(),
        };
    }

    protected static function formatEventLabel(Event $event): string
    {
        $date = $event->start_date?->format('d.m.Y');

        return trim(sprintf('%s%s (#%d)', $event->name, $date ? ' • ' . $date : '', $event->getKey()));
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
        $dayLabel = $programPoint->day ? ' • dzień ' . $programPoint->day : '';

        return trim($pointName . ($eventName ? ' • ' . $eventName : '') . $dayLabel . ' (#' . $programPoint->getKey() . ')');
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

        $name = $document->name ?: ('Dokument #' . $document->getKey());

        return trim($name . ($eventName ? ' • ' . $eventName : ''));
    }

    protected static function formatSettlementCostLabel(EventSettlementCost $cost): string
    {
        $eventName = $cost->relationLoaded('settlement')
            ? $cost->settlement?->event?->name
            : $cost->settlement?->event?->name;

        return trim(($cost->name ?: 'Pozycja kosztu') . ($eventName ? ' • ' . $eventName : '') . ' (#' . $cost->getKey() . ')');
    }

    protected static function formatSettlementDocumentLabel(EventSettlementDocument $document): string
    {
        $eventName = $document->relationLoaded('settlement')
            ? $document->settlement?->event?->name
            : $document->settlement?->event?->name;

        $docNumber = $document->document_number ?: ('Dokument #' . $document->getKey());

        return trim($docNumber . ($eventName ? ' • ' . $eventName : ''));
    }

    protected static function formatSettlementPaymentLabel(EventSettlementParticipantPayment $payment): string
    {
        $eventName = $payment->relationLoaded('settlement')
            ? $payment->settlement?->event?->name
            : $payment->settlement?->event?->name;

        $label = $payment->participant_name ?: 'Wpłata uczestnika';

        return trim($label . ($eventName ? ' • ' . $eventName : '') . ' (#' . $payment->getKey() . ')');
    }

    protected static function formatPilotCashLabel(PilotCashPreparation $cash): string
    {
        $eventName = $cash->relationLoaded('settlement')
            ? $cash->settlement?->event?->name
            : $cash->settlement?->event?->name;
        $currency = $cash->relationLoaded('currency') ? $cash->currency?->symbol : $cash->currency?->symbol;

        return trim('Gotówka pilota' . ($currency ? ' ' . $currency : '') . ($eventName ? ' • ' . $eventName : '') . ' (#' . $cash->getKey() . ')');
    }
}
