<?php

namespace App\Services;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\VendorInvoiceResource;
use App\Models\Contractor;
use App\Models\Event;
use App\Models\EventHotelStay;
use App\Models\EventProgramPoint;
use App\Models\EventSettlementCost;
use App\Models\VendorInvoice;
use App\Services\Invoices\ContractorResolver;
use App\Support\CurrencyAmountDisplay;
use App\Support\MoneyFormatter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ContractorInvolvementOverviewService
{
    public const ROLE_ORDERING = 'ordering';

    public const ROLE_CLIENT = 'client';

    public const ROLE_PROGRAM_POINT = 'program_point';

    public const ROLE_SETTLEMENT = 'settlement';

    public const ROLE_HOTEL = 'hotel';

    public const ROLE_TRANSPORT = 'transport';

    public const ROLE_DRIVER = 'driver';

    public const ROLE_PILOT = 'pilot';

    /** @var array<string, string> */
    public const ROLE_LABELS = [
        self::ROLE_ORDERING => 'Zamawiający',
        self::ROLE_CLIENT => 'Klient',
        self::ROLE_PROGRAM_POINT => 'Punkt programu',
        self::ROLE_SETTLEMENT => 'Rozliczenie',
        self::ROLE_HOTEL => 'Hotel',
        self::ROLE_TRANSPORT => 'Przewoźnik',
        self::ROLE_DRIVER => 'Kierowca',
        self::ROLE_PILOT => 'Pilot',
    ];

    /** @var array<string, string> badge variant: success|warning|danger|accent|muted */
    public const ROLE_VARIANTS = [
        self::ROLE_ORDERING => 'accent',
        self::ROLE_CLIENT => 'accent',
        self::ROLE_PROGRAM_POINT => 'success',
        self::ROLE_SETTLEMENT => 'warning',
        self::ROLE_HOTEL => 'muted',
        self::ROLE_TRANSPORT => 'accent',
        self::ROLE_DRIVER => 'muted',
        self::ROLE_PILOT => 'success',
    ];

    /**
     * @return array{
     *     summary: array{
     *         events_count: int,
     *         points_count: int,
     *         costs_count: int,
     *         invoices_count: int,
     *         planned_pln_label: string,
     *         due_pln_label: string,
     *         due_danger: bool
     *     },
     *     events: list<array<string, mixed>>,
     *     points: list<array<string, mixed>>,
     *     settlement_costs: list<array<string, mixed>>,
     *     vendor_invoices: list<array<string, mixed>>
     * }
     */
    public function for(Contractor $contractor): array
    {
        $rolesByEventId = $this->collectRolesByEventId($contractor);
        $events = $this->loadEvents(array_keys($rolesByEventId));
        $points = $this->buildPoints($contractor);
        $settlementCosts = $this->buildSettlementCosts($contractor);
        $vendorInvoices = $this->buildVendorInvoices($contractor);

        $eventRows = [];
        foreach ($events as $event) {
            $roles = $rolesByEventId[(int) $event->id] ?? [];
            $eventRows[] = $this->mapEventRow($event, $roles);
        }

        usort($eventRows, function (array $a, array $b): int {
            return strcmp((string) ($b['start_date_sort'] ?? ''), (string) ($a['start_date_sort'] ?? ''));
        });

        $plannedPln = 0.0;
        $duePln = 0.0;
        foreach ($settlementCosts as $cost) {
            $plannedPln += (float) ($cost['planned_pln'] ?? 0);
            $duePln += (float) ($cost['due_pln'] ?? 0);
        }
        foreach ($vendorInvoices as $invoice) {
            $duePln += (float) ($invoice['due_pln'] ?? 0);
        }

        return [
            'summary' => [
                'events_count' => count($eventRows),
                'points_count' => count($points),
                'costs_count' => count($settlementCosts),
                'invoices_count' => count($vendorInvoices),
                'planned_pln_label' => MoneyFormatter::format($plannedPln, 'PLN', 0),
                'due_pln_label' => $duePln > 0.009
                    ? MoneyFormatter::format($duePln, 'PLN', 0)
                    : '—',
                'due_danger' => $duePln > 0.009,
            ],
            'events' => $eventRows,
            'points' => $points,
            'settlement_costs' => $settlementCosts,
            'vendor_invoices' => $vendorInvoices,
            'rows' => $this->buildUnifiedRows($eventRows, $points, $settlementCosts, $vendorInvoices),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @param  list<array<string, mixed>>  $points
     * @param  list<array<string, mixed>>  $costs
     * @param  list<array<string, mixed>>  $invoices
     * @return list<array<string, mixed>>
     */
    public function buildUnifiedRows(array $events, array $points, array $costs, array $invoices): array
    {
        $rows = [];

        foreach ($events as $event) {
            $roleKeys = array_column($event['roles'] ?? [], 'key');
            $roleLabels = array_column($event['roles'] ?? [], 'label');
            $rows[] = [
                'id' => 'event:'.$event['id'],
                'kind' => 'event',
                'kind_label' => 'Impreza',
                'kind_variant' => 'accent',
                'title' => (string) $event['name'],
                'subtitle' => $event['code'] ?? null,
                'event_name' => (string) $event['name'],
                'event_code' => $event['code'] ?? null,
                'event_url' => $event['url'] ?? null,
                'relation_labels' => $roleLabels,
                'relation_keys' => $roleKeys,
                'status_label' => $roleLabels !== [] ? implode(', ', $roleLabels) : 'Udział',
                'status_variant' => 'muted',
                'amount_label' => '—',
                'date_label' => (string) ($event['dates_label'] ?? '—'),
                'date_sort' => (string) ($event['start_date_sort'] ?? ''),
                'url' => $event['url'] ?? null,
                'search_blob' => mb_strtolower(implode(' ', array_filter([
                    $event['name'] ?? null,
                    $event['code'] ?? null,
                    $event['client_name'] ?? null,
                    implode(' ', $roleLabels),
                ]))),
            ];
        }

        foreach ($points as $point) {
            $rows[] = [
                'id' => 'point:'.$point['id'],
                'kind' => 'point',
                'kind_label' => 'Punkt',
                'kind_variant' => 'success',
                'title' => (string) $point['name'],
                'subtitle' => $point['day_label'] ?? null,
                'event_name' => $point['event_name'] ?? null,
                'event_code' => $point['event_code'] ?? null,
                'event_url' => $point['event_url'] ?? null,
                'relation_labels' => ['Punkt programu'],
                'relation_keys' => [self::ROLE_PROGRAM_POINT],
                'status_label' => (string) ($point['payment_label'] ?? '—'),
                'status_variant' => match ((string) ($point['payment_color'] ?? 'gray')) {
                    'green' => 'success',
                    'red' => 'danger',
                    'orange' => 'warning',
                    default => 'muted',
                },
                'status_tooltip' => $point['payment_tooltip'] ?? null,
                'amount_label' => '—',
                'date_label' => (string) ($point['date_label'] ?? $point['day_label'] ?? '—'),
                'date_sort' => $point['date_label']
                    ? (\DateTime::createFromFormat('d.m.Y', (string) $point['date_label'])?->format('Y-m-d') ?? '')
                    : '',
                'url' => $point['program_url'] ?? $point['event_url'] ?? null,
                'search_blob' => mb_strtolower(implode(' ', array_filter([
                    $point['name'] ?? null,
                    $point['event_name'] ?? null,
                    $point['event_code'] ?? null,
                    $point['day_label'] ?? null,
                    $point['payment_label'] ?? null,
                ]))),
            ];
        }

        foreach ($costs as $cost) {
            $rows[] = [
                'id' => 'cost:'.$cost['id'],
                'kind' => 'cost',
                'kind_label' => 'Koszt',
                'kind_variant' => 'warning',
                'title' => (string) $cost['name'],
                'subtitle' => $cost['source_label'] ?? null,
                'event_name' => $cost['event_name'] ?? null,
                'event_code' => $cost['event_code'] ?? null,
                'event_url' => $cost['url'] ?? null,
                'relation_labels' => [(string) ($cost['source_label'] ?? 'Rozliczenie')],
                'relation_keys' => [self::ROLE_SETTLEMENT],
                'status_label' => (string) ($cost['status_label'] ?? '—'),
                'status_variant' => (string) ($cost['status_variant'] ?? 'muted'),
                'amount_label' => (string) ($cost['amount_label'] ?? '—'),
                'date_label' => '—',
                'date_sort' => '',
                'url' => $cost['url'] ?? null,
                'search_blob' => mb_strtolower(implode(' ', array_filter([
                    $cost['name'] ?? null,
                    $cost['source_label'] ?? null,
                    $cost['event_name'] ?? null,
                    $cost['event_code'] ?? null,
                    $cost['status_label'] ?? null,
                    $cost['amount_label'] ?? null,
                ]))),
            ];
        }

        foreach ($invoices as $invoice) {
            $rows[] = [
                'id' => 'invoice:'.$invoice['id'],
                'kind' => 'invoice',
                'kind_label' => 'Faktura',
                'kind_variant' => 'muted',
                'title' => (string) $invoice['name'],
                'subtitle' => $invoice['ksef'] ? 'KSeF' : null,
                'event_name' => $invoice['event_name'] ?? null,
                'event_code' => $invoice['event_code'] ?? null,
                'event_url' => $invoice['event_url'] ?? null,
                'relation_labels' => ['Faktura kosztowa'],
                'relation_keys' => [self::ROLE_SETTLEMENT],
                'status_label' => (string) ($invoice['status_label'] ?? '—'),
                'status_variant' => (string) ($invoice['status_variant'] ?? 'muted'),
                'amount_label' => (string) ($invoice['amount_label'] ?? '—'),
                'date_label' => (string) ($invoice['due_date_label'] ?? $invoice['issue_date_label'] ?? '—'),
                'date_sort' => $invoice['due_date_label']
                    ? (\DateTime::createFromFormat('d.m.Y', (string) $invoice['due_date_label'])?->format('Y-m-d') ?? '')
                    : ($invoice['issue_date_label']
                        ? (\DateTime::createFromFormat('d.m.Y', (string) $invoice['issue_date_label'])?->format('Y-m-d') ?? '')
                        : ''),
                'url' => $invoice['url'] ?? null,
                'search_blob' => mb_strtolower(implode(' ', array_filter([
                    $invoice['name'] ?? null,
                    $invoice['ksef'] ?? null,
                    $invoice['event_name'] ?? null,
                    $invoice['event_code'] ?? null,
                    $invoice['status_label'] ?? null,
                    $invoice['amount_label'] ?? null,
                ]))),
            ];
        }

        usort($rows, function (array $a, array $b): int {
            $dateCmp = strcmp((string) ($b['date_sort'] ?? ''), (string) ($a['date_sort'] ?? ''));
            if ($dateCmp !== 0) {
                return $dateCmp;
            }

            return strcmp((string) $a['id'], (string) $b['id']);
        });

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function filterUnifiedRows(
        array $rows,
        string $search = '',
        string $kind = 'all',
        string $role = 'all',
        string $status = 'all',
    ): array {
        $search = mb_strtolower(trim($search));

        $filtered = array_values(array_filter($rows, function (array $row) use ($search, $kind, $role, $status): bool {
            if ($kind !== 'all' && ($row['kind'] ?? '') !== $kind) {
                return false;
            }

            if ($role !== 'all') {
                $keys = $row['relation_keys'] ?? [];
                if (! in_array($role, $keys, true)) {
                    return false;
                }
            }

            if ($status === 'open') {
                if (! in_array($row['kind'] ?? '', ['cost', 'invoice', 'point'], true)) {
                    return false;
                }
                if (($row['status_variant'] ?? '') === 'success') {
                    return false;
                }
            }

            if ($status === 'paid') {
                if (($row['status_variant'] ?? '') !== 'success') {
                    return false;
                }
            }

            if ($search !== '' && ! str_contains((string) ($row['search_blob'] ?? ''), $search)) {
                return false;
            }

            return true;
        }));

        return $filtered;
    }

    /**
     * @return array<int, list<string>>
     */
    protected function collectRolesByEventId(Contractor $contractor): array
    {
        $id = (int) $contractor->id;
        /** @var array<int, array<string, true>> $map */
        $map = [];

        $add = function (int $eventId, string $role) use (&$map): void {
            if ($eventId <= 0) {
                return;
            }
            $map[$eventId][$role] = true;
        };

        if (Schema::hasTable('event_contractor')) {
            foreach ($contractor->orderingEvents()->pluck('events.id') as $eventId) {
                $add((int) $eventId, self::ROLE_ORDERING);
            }
        }

        if (Schema::hasColumn('events', 'contractor_id')) {
            Event::query()
                ->where('contractor_id', $id)
                ->pluck('id')
                ->each(fn ($eventId) => $add((int) $eventId, self::ROLE_CLIENT));
        }

        if (Schema::hasTable('event_program_points')) {
            EventProgramPoint::query()
                ->where('contractor_id', $id)
                ->whereNotNull('event_id')
                ->pluck('event_id')
                ->each(fn ($eventId) => $add((int) $eventId, self::ROLE_PROGRAM_POINT));
        }

        if (Schema::hasTable('event_settlement_costs') && Schema::hasTable('event_settlements')) {
            EventSettlementCost::query()
                ->where('contractor_id', $id)
                ->whereHas('settlement', fn ($q) => $q->whereNotNull('event_id'))
                ->with('settlement:id,event_id')
                ->get(['id', 'settlement_id'])
                ->each(function (EventSettlementCost $cost) use ($add): void {
                    $add((int) ($cost->settlement?->event_id ?? 0), self::ROLE_SETTLEMENT);
                });
        }

        if (Schema::hasTable('event_hotel_stays')) {
            EventHotelStay::query()
                ->where('contractor_id', $id)
                ->whereNotNull('event_id')
                ->pluck('event_id')
                ->each(fn ($eventId) => $add((int) $eventId, self::ROLE_HOTEL));
        }

        if (Schema::hasColumn('events', 'transport_contractor_id')) {
            Event::query()
                ->where('transport_contractor_id', $id)
                ->pluck('id')
                ->each(fn ($eventId) => $add((int) $eventId, self::ROLE_TRANSPORT));
        }

        if (Schema::hasColumn('events', 'driver_contractor_id')) {
            Event::query()
                ->where('driver_contractor_id', $id)
                ->pluck('id')
                ->each(fn ($eventId) => $add((int) $eventId, self::ROLE_DRIVER));
        }

        if (Schema::hasColumn('events', 'pilot_contractor_id')) {
            Event::query()
                ->where('pilot_contractor_id', $id)
                ->pluck('id')
                ->each(fn ($eventId) => $add((int) $eventId, self::ROLE_PILOT));
        }

        $result = [];
        foreach ($map as $eventId => $roles) {
            $ordered = [];
            foreach (array_keys(self::ROLE_LABELS) as $role) {
                if (isset($roles[$role])) {
                    $ordered[] = $role;
                }
            }
            $result[$eventId] = $ordered;
        }

        return $result;
    }

    /**
     * @param  list<int>  $eventIds
     * @return Collection<int, Event>
     */
    protected function loadEvents(array $eventIds): Collection
    {
        if ($eventIds === []) {
            return collect();
        }

        return Event::query()
            ->whereIn('id', $eventIds)
            ->get(['id', 'name', 'code', 'start_date', 'end_date', 'client_name']);
    }

    /**
     * @param  list<string>  $roles
     * @return array<string, mixed>
     */
    protected function mapEventRow(Event $event, array $roles): array
    {
        $url = null;
        try {
            $url = EventResource::getUrl('edit', ['record' => $event]);
        } catch (\Throwable) {
            $url = null;
        }

        $dates = $this->formatEventDates($event);

        return [
            'id' => (int) $event->id,
            'name' => (string) ($event->name ?: 'Impreza #'.$event->id),
            'code' => filled($event->code) ? (string) $event->code : null,
            'client_name' => filled($event->client_name) ? (string) $event->client_name : null,
            'dates_label' => $dates,
            'start_date_sort' => $event->start_date?->format('Y-m-d') ?? '',
            'url' => $url,
            'roles' => array_map(fn (string $role): array => [
                'key' => $role,
                'label' => self::ROLE_LABELS[$role] ?? $role,
                'variant' => self::ROLE_VARIANTS[$role] ?? 'muted',
            ], $roles),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function buildPoints(Contractor $contractor): array
    {
        if (! Schema::hasTable('event_program_points')) {
            return [];
        }

        $points = EventProgramPoint::query()
            ->where('contractor_id', $contractor->id)
            ->with(['event:id,name,code,start_date'])
            ->orderByDesc('id')
            ->get();

        $resolver = app(ProgramPointPaymentStatusResolver::class);
        $rows = [];

        foreach ($points as $point) {
            $event = $point->event;
            $badge = $resolver->resolve($point, $event);
            $eventUrl = null;
            $programUrl = null;

            if ($event) {
                try {
                    $eventUrl = EventResource::getUrl('edit', ['record' => $event]);
                } catch (\Throwable) {
                    $eventUrl = null;
                }
                try {
                    $programUrl = EventResource::getUrl('edit-program', ['record' => $event]);
                } catch (\Throwable) {
                    $programUrl = $eventUrl;
                }
            }

            $dayLabel = $point->day !== null ? 'Dzień '.(int) $point->day : null;
            $pointDate = null;
            if ($event?->start_date && $point->day) {
                $pointDate = $event->start_date->copy()->addDays(max(0, (int) $point->day - 1))->format('d.m.Y');
            }

            $rows[] = [
                'id' => (int) $point->id,
                'name' => (string) ($point->name ?: 'Punkt #'.$point->id),
                'day_label' => $dayLabel,
                'date_label' => $pointDate,
                'event_id' => $event ? (int) $event->id : null,
                'event_name' => $event?->name,
                'event_code' => $event?->code,
                'event_url' => $eventUrl,
                'program_url' => $programUrl,
                'payment_label' => $this->shortPaymentLabel((string) ($badge['code'] ?? ''), (string) ($badge['color'] ?? 'gray')),
                'payment_tooltip' => (string) ($badge['tooltip'] ?? ''),
                'payment_color' => (string) ($badge['color'] ?? 'gray'),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function buildSettlementCosts(Contractor $contractor): array
    {
        if (! Schema::hasTable('event_settlement_costs')) {
            return [];
        }

        $costs = EventSettlementCost::query()
            ->where('contractor_id', $contractor->id)
            ->with([
                'settlement:id,event_id,status',
                'settlement.event:id,name,code',
                'plannedCurrency',
                'actualCurrency',
            ])
            ->orderByDesc('id')
            ->get();

        $rows = [];
        foreach ($costs as $cost) {
            $event = $cost->settlement?->event;
            $financeUrl = null;
            if ($event) {
                try {
                    $financeUrl = EventResource::getUrl('finance', ['record' => $event]);
                } catch (\Throwable) {
                    try {
                        $financeUrl = EventResource::getUrl('edit', ['record' => $event]);
                    } catch (\Throwable) {
                        $financeUrl = null;
                    }
                }
            }

            $plannedAmount = (float) ($cost->planned_amount ?? 0);
            $plannedPln = (float) ($cost->planned_amount_pln ?? 0);
            $actualAmount = (float) ($cost->actual_amount ?? 0);
            $actualPln = (float) ($cost->actual_amount_pln ?? 0);
            $status = (string) ($cost->payment_status ?? 'planned');
            $duePln = $this->costDuePln($cost);

            $amountLabel = $plannedAmount > 0.009
                ? CurrencyAmountDisplay::format(
                    $plannedAmount,
                    $cost->plannedCurrency,
                    (bool) $cost->planned_convert_to_pln,
                    0
                )
                : ($actualAmount > 0.009
                    ? CurrencyAmountDisplay::format($actualAmount, $cost->actualCurrency, true, 0)
                    : '—');

            $rows[] = [
                'id' => (int) $cost->id,
                'kind' => 'cost',
                'name' => (string) ($cost->name ?: 'Koszt #'.$cost->id),
                'source_label' => EventSettlementCost::$sourceTypeLabels[$cost->source_type] ?? (string) $cost->source_type,
                'status' => $status,
                'status_label' => EventSettlementCost::$paymentStatuses[$status] ?? $status,
                'status_variant' => $this->paymentStatusVariant($status),
                'amount_label' => $amountLabel,
                'planned_pln' => $plannedPln > 0.009 ? $plannedPln : ($actualPln > 0.009 ? $actualPln : 0.0),
                'due_pln' => $duePln,
                'event_id' => $event ? (int) $event->id : null,
                'event_name' => $event?->name,
                'event_code' => $event?->code,
                'url' => $financeUrl,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function buildVendorInvoices(Contractor $contractor): array
    {
        if (! Schema::hasTable('vendor_invoices')) {
            return [];
        }

        $nip = ContractorResolver::normalizeNip($contractor->nip);

        $query = VendorInvoice::query()
            ->with(['event:id,name,code'])
            ->where(function ($q) use ($contractor, $nip): void {
                $q->where('contractor_id', $contractor->id);
                if ($nip) {
                    $q->orWhere('seller_nip', $nip);
                }
            })
            ->orderByDesc('issue_date')
            ->orderByDesc('id');

        $rows = [];
        foreach ($query->get() as $invoice) {
            $status = (string) ($invoice->payment_status ?? 'due');
            $gross = (float) ($invoice->gross_amount ?? 0);
            $paid = (float) ($invoice->paid_amount ?? 0);
            $due = max(0, $gross - $paid);
            if (in_array($status, ['paid', 'cancelled'], true)) {
                $due = 0.0;
            }

            $url = null;
            try {
                $url = VendorInvoiceResource::getUrl('edit', ['record' => $invoice]);
            } catch (\Throwable) {
                $url = null;
            }

            $eventUrl = null;
            if ($invoice->event) {
                try {
                    $eventUrl = EventResource::getUrl('edit', ['record' => $invoice->event]);
                } catch (\Throwable) {
                    $eventUrl = null;
                }
            }

            $currency = strtoupper((string) ($invoice->currency ?: 'PLN'));

            $rows[] = [
                'id' => (int) $invoice->id,
                'kind' => 'invoice',
                'name' => (string) ($invoice->invoice_number ?: 'Faktura #'.$invoice->id),
                'ksef' => filled($invoice->ksef_number) ? (string) $invoice->ksef_number : null,
                'status' => $status,
                'status_label' => VendorInvoice::$paymentStatuses[$status] ?? $status,
                'status_variant' => $this->invoiceStatusVariant($status),
                'amount_label' => MoneyFormatter::format($gross, $currency, 2),
                'due_date_label' => $invoice->due_date?->format('d.m.Y'),
                'issue_date_label' => $invoice->issue_date?->format('d.m.Y'),
                'due_pln' => $currency === 'PLN' ? $due : 0.0,
                'event_id' => $invoice->event ? (int) $invoice->event->id : null,
                'event_name' => $invoice->event?->name,
                'event_code' => $invoice->event?->code,
                'event_url' => $eventUrl,
                'url' => $url,
            ];
        }

        return $rows;
    }

    protected function costDuePln(EventSettlementCost $cost): float
    {
        $status = (string) ($cost->payment_status ?? '');
        if (in_array($status, ['paid', 'cancelled'], true)) {
            return 0.0;
        }

        $planned = (float) ($cost->planned_amount_pln ?? 0);
        $actual = (float) ($cost->actual_amount_pln ?? 0);

        if ($planned > 0.009) {
            return max(0, $planned - $actual);
        }

        return 0.0;
    }

    protected function paymentStatusVariant(string $status): string
    {
        return match ($status) {
            'paid' => 'success',
            'cancelled' => 'muted',
            'partially_paid', 'advance_paid', 'reserved' => 'warning',
            'advance_required', 'reservation_required' => 'danger',
            default => 'muted',
        };
    }

    protected function invoiceStatusVariant(string $status): string
    {
        return match ($status) {
            'paid' => 'success',
            'partial' => 'warning',
            'overdue' => 'danger',
            'cancelled' => 'muted',
            default => 'warning',
        };
    }

    protected function formatEventDates(Event $event): string
    {
        if ($event->start_date && $event->end_date) {
            if ($event->start_date->equalTo($event->end_date)) {
                return $event->start_date->format('d.m.Y');
            }

            return $event->start_date->format('d.m.Y').' – '.$event->end_date->format('d.m.Y');
        }

        if ($event->start_date) {
            return $event->start_date->format('d.m.Y');
        }

        return '—';
    }

    protected function shortPaymentLabel(string $code, string $color): string
    {
        return match ($color) {
            'green' => 'Opłacone',
            'red' => 'Przeterminowane',
            'orange' => 'Do zapłaty',
            default => $code !== '' && $code !== 'N/A' ? $code : 'Brak płatności',
        };
    }
}
