<?php

namespace App\Services\Invoices;

use App\Models\Contract;
use App\Models\Event;
use App\Models\EventAgreement;
use App\Models\EventProgramPoint;
use App\Models\VendorInvoice;
use Carbon\Carbon;

class VendorInvoiceMatcher
{
    private const EVENT_CODE_PATTERN = '/\b(\d{2}-?[A-Z0-9]{6})\b/i';

    private const AGREEMENT_PATTERN = '/\b(\d{8}\/\d+)\b/';

    public function __construct(
        private readonly ContractorResolver $contractorResolver = new ContractorResolver,
    ) {}

    public function match(VendorInvoice $invoice): VendorInvoice
    {
        $wasManual = $invoice->matching_status === 'manual';
        $previousEventId = $invoice->event_id;
        $previousProgramPointId = $invoice->event_program_point_id;

        $hints = [];
        $contractor = $this->contractorResolver->resolveFromInvoice($invoice);
        if ($contractor) {
            $invoice->contractor_id = $contractor->id;
            $hints[] = ['rule' => 'contractor_nip', 'confidence' => 0.9, 'contractor_id' => $contractor->id];
        }

        $searchText = $invoice->searchableText();
        if ($invoice->original_filename) {
            $searchText .= ' '.$invoice->original_filename;
        }

        $programPointHint = null;
        $event = $this->matchByEventCode($searchText, $hints);
        if (! $event) {
            $event = $this->matchByAgreementNumber($searchText, $hints);
        }
        if (! $event && $contractor) {
            [$event, $programPointHint] = $this->matchByContractorAndDate($contractor->id, $invoice->sale_date ?? $invoice->issue_date, $hints);
        }
        if (! $event) {
            [$event, $programPointHint] = $this->matchBySaleDateAndSellerName($invoice, $hints);
        }

        if ($event) {
            $invoice->event_id = $event->id;
            $invoice->matching_status = $wasManual ? 'manual' : 'auto_matched';

            if ($wasManual && $previousProgramPointId) {
                $existingPoint = EventProgramPoint::query()->find($previousProgramPointId);
                if ($existingPoint && (int) $existingPoint->event_id === (int) $event->id) {
                    $invoice->event_program_point_id = $existingPoint->id;
                    $hints[] = ['rule' => 'manual_program_point_preserved', 'confidence' => 1.0, 'program_point_id' => $existingPoint->id];
                } else {
                    $invoice->event_program_point_id = null;
                }
            } else {
                if ((int) $previousEventId !== (int) $event->id) {
                    $invoice->event_program_point_id = null;
                }

                $programPoint = $programPointHint ?? $this->matchProgramPoint($event, $contractor?->id, $invoice->seller_name);
                if ($programPoint) {
                    $invoice->event_program_point_id = $programPoint->id;
                    $hints[] = ['rule' => 'program_point', 'confidence' => 0.7, 'program_point_id' => $programPoint->id];
                } elseif ((int) $previousEventId !== (int) $event->id) {
                    $invoice->event_program_point_id = null;
                }
            }
        } elseif (! $wasManual) {
            $invoice->matching_status = 'unmatched';
            $invoice->event_id = null;
            $invoice->event_program_point_id = null;
        } elseif ($previousEventId && ! $invoice->event_id) {
            $invoice->event_id = $previousEventId;
            $invoice->event_program_point_id = $previousProgramPointId;
        }

        $invoice->matching_hints = $hints;
        $invoice->save();

        if (! $wasManual) {
            $invoice->syncProgramPointLinks(
                $invoice->event_program_point_id ? [(int) $invoice->event_program_point_id] : []
            );
        } elseif ($invoice->event_program_point_id && \Illuminate\Support\Facades\Schema::hasTable('vendor_invoice_program_point')) {
            // Ręczne multi-linki zostawiamy; upewniamy się, że główny punkt jest w pivocie.
            $invoice->programPoints()->syncWithoutDetaching([(int) $invoice->event_program_point_id]);
        }

        return $invoice->fresh(['event', 'contractor', 'programPoint', 'programPoints', 'lines']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $hints
     */
    private function matchByEventCode(string $text, array &$hints): ?Event
    {
        if (! preg_match_all(self::EVENT_CODE_PATTERN, $text, $matches)) {
            return null;
        }

        foreach (array_unique(array_map('strtoupper', $matches[1])) as $code) {
            $event = Event::query()->where('code', $code)->first();
            if ($event) {
                $hints[] = ['rule' => 'event_code', 'confidence' => 0.95, 'code' => $code];

                return $event;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $hints
     */
    private function matchByAgreementNumber(string $text, array &$hints): ?Event
    {
        if (! preg_match(self::AGREEMENT_PATTERN, $text, $matches)) {
            return null;
        }

        $number = $matches[1];
        $agreement = EventAgreement::query()
            ->where(function ($query) use ($number): void {
                $query->where('agreement_number', 'like', '%'.$number.'%')
                    ->orWhere('agreement_number', $number);
            })
            ->first();

        if ($agreement?->event_id) {
            $hints[] = ['rule' => 'agreement_number', 'confidence' => 0.85, 'agreement_number' => $number];

            return Event::find($agreement->event_id);
        }

        $contract = Contract::query()
            ->where(function ($query) use ($number): void {
                $query->where('contract_number', 'like', '%'.$number.'%')
                    ->orWhere('agreement_number', 'like', '%'.$number.'%');
            })
            ->first();

        if ($contract?->event_id) {
            $hints[] = ['rule' => 'contract_number', 'confidence' => 0.85, 'number' => $number];

            return Event::find($contract->event_id);
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $hints
     * @return array{0: ?Event, 1: ?EventProgramPoint}
     */
    private function matchByContractorAndDate(int $contractorId, ?Carbon $date, array &$hints): array
    {
        if (! $date) {
            return [null, null];
        }

        $from = $date->copy()->subDays(7);
        $to = $date->copy()->addDays(7);

        $points = EventProgramPoint::query()
            ->where('contractor_id', $contractorId)
            ->whereHas('event', fn ($q) => $q->whereBetween('start_date', [$from, $to])
                ->orWhereBetween('end_date', [$from, $to])
                ->orWhere(fn ($q2) => $q2->where('start_date', '<=', $date)->where('end_date', '>=', $date)))
            ->with('event')
            ->get();

        $eventIds = $points->pluck('event_id')->unique();

        if ($eventIds->count() === 1) {
            $hints[] = ['rule' => 'contractor_date_window', 'confidence' => 0.75, 'contractor_id' => $contractorId];
            $point = $points->first();

            return [$point?->event, $point];
        }

        return [null, null];
    }

    /**
     * @param  array<int, array<string, mixed>>  $hints
     * @return array{0: ?Event, 1: ?EventProgramPoint}
     */
    private function matchBySaleDateAndSellerName(VendorInvoice $invoice, array &$hints): array
    {
        $date = $invoice->sale_date ?? $invoice->issue_date;
        if (! $date || ! $invoice->seller_name) {
            return [null, null];
        }

        $events = Event::query()
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->with(['programPoints.contractor'])
            ->get();

        if ($events->isEmpty()) {
            return [null, null];
        }

        $sellerNorm = $this->normalizeName($invoice->seller_name);
        $bestEvent = null;
        $bestPoint = null;
        $bestScore = 0;

        foreach ($events as $event) {
            foreach ($event->programPoints as $point) {
                $contractorName = $point->contractor?->name ?? $point->name ?? '';
                similar_text($sellerNorm, $this->normalizeName($contractorName), $percent);
                if ($percent > $bestScore && $percent >= 60) {
                    $bestScore = $percent;
                    $bestEvent = $event;
                    $bestPoint = $point;
                }
            }
        }

        if ($bestEvent) {
            $hints[] = ['rule' => 'date_name_similarity', 'confidence' => round($bestScore / 100, 2)];

            return [$bestEvent, $bestPoint];
        }

        return [null, null];
    }

    private function matchProgramPoint(Event $event, ?int $contractorId, ?string $sellerName): ?EventProgramPoint
    {
        if ($contractorId) {
            $points = $event->programPoints()->where('contractor_id', $contractorId)->get();
            if ($points->count() === 1) {
                return $points->first();
            }
            if ($points->count() > 1 && $sellerName) {
                $sellerNorm = $this->normalizeName($sellerName);
                $best = null;
                $bestScore = 0;
                foreach ($points as $point) {
                    $name = $point->contractor?->name ?? $point->name ?? '';
                    similar_text($sellerNorm, $this->normalizeName($name), $percent);
                    if ($percent > $bestScore) {
                        $bestScore = $percent;
                        $best = $point;
                    }
                }

                if ($bestScore >= 50) {
                    return $best;
                }
            }
        }

        if (! $sellerName) {
            return null;
        }

        $sellerNorm = $this->normalizeName($sellerName);
        $best = null;
        $bestScore = 0;

        foreach ($event->programPoints as $point) {
            $name = $point->contractor?->name ?? $point->name ?? '';
            similar_text($sellerNorm, $this->normalizeName($name), $percent);
            if ($percent > $bestScore) {
                $bestScore = $percent;
                $best = $point;
            }
        }

        return $bestScore >= 50 ? $best : null;
    }

    private function normalizeName(string $name): string
    {
        $name = mb_strtoupper($name);
        $name = preg_replace('/\s+/', ' ', $name);

        return trim($name);
    }
}
