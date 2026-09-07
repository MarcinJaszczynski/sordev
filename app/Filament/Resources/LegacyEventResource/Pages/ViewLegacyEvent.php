<?php

namespace App\Filament\Resources\LegacyEventResource\Pages;

use App\Filament\Resources\ContractorResource;
use App\Filament\Resources\LegacyEventResource;
use App\Models\Event;
use App\Models\LegacyEvent;
use App\Services\Legacy\LegacyContractorArchiveStats;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ViewLegacyEvent extends ViewRecord
{
    protected static string $resource = LegacyEventResource::class;

    protected static string $view = 'filament.resources.legacy-event-resource.pages.view-legacy-event';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('purchaser_history')
                ->label('Historia zamawiającego')
                ->icon('heroicon-o-clock')
                ->color('info')
                ->modalHeading('Historia zamawiającego (Event + Archiwum)')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Zamknij')
                ->modalWidth('7xl')
                ->modalContent(function () {
                    return view('filament.modals.legacy-purchaser-history', [
                        'legacyEvents' => $this->getMatchingLegacyEvents(),
                        'events' => $this->getMatchingEvents(),
                        'criteria' => $this->getNormalizedCriteria(),
                    ]);
                }),
            Actions\Action::make('open_contractor')
                ->label('Kartę kontrahenta')
                ->icon('heroicon-o-building-office-2')
                ->color('gray')
                ->visible(fn (): bool => filled($this->getRecord()->contractor_id))
                ->url(fn (): string => ContractorResource::getUrl('edit', ['record' => $this->getRecord()->contractor_id])),
            Actions\Action::make('back')
                ->label('Powrót do listy')
                ->url(LegacyEventResource::getUrl('index')),
        ];
    }

    /**
     * @return array{
     *     name: string,
     *     code: ?string,
     *     status: ?string,
     *     client: ?string,
     *     dates: string,
     *     participants: int,
     *     guardians: int,
     *     free: int,
     *     duration: ?int,
     *     contractor_url: ?string,
     *     archive: array<string, mixed>
     * }
     */
    public function legacyPageSummary(): array
    {
        /** @var LegacyEvent $record */
        $record = $this->getRecord();

        $start = $record->start_datetime?->format('d.m.Y');
        $end = $record->end_datetime?->format('d.m.Y');
        $dates = match (true) {
            $start && $end && $start !== $end => $start.' – '.$end,
            (bool) $start => $start,
            default => '—',
        };

        $archive = ['total' => 0, 'completed' => 0, 'cancelled' => 0, 'participants_completed' => 0, 'years_label' => '—'];
        if ($record->contractor_id) {
            $contractor = $record->contractor;
            if ($contractor) {
                $archive = app(LegacyContractorArchiveStats::class)->forContractor($contractor);
            }
        }

        return [
            'name' => (string) $record->name,
            'code' => $record->office_id,
            'status' => $record->legacy_status,
            'client' => $record->client_name,
            'dates' => $dates,
            'participants' => (int) ($record->participant_count ?? 0),
            'guardians' => (int) ($record->guardians_count ?? 0),
            'free' => (int) ($record->free_count ?? 0),
            'duration' => $record->duration_days,
            'contractor_url' => $record->contractor_id
                ? ContractorResource::getUrl('edit', ['record' => $record->contractor_id])
                : null,
            'archive' => $archive,
        ];
    }

    private function getMatchingLegacyEvents(): Collection
    {
        /** @var LegacyEvent $record */
        $record = $this->record;
        $criteria = $this->getNormalizedCriteria();

        return LegacyEvent::query()
            ->select([
                'id',
                'legacy_id',
                'office_id',
                'name',
                'legacy_status',
                'start_datetime',
                'end_datetime',
                'participant_count',
                'client_name',
                'client_phone',
                'client_email',
            ])
            ->where('id', '!=', $record->id)
            ->where(function (Builder $query) use ($criteria, $record): void {
                if (filled($record->contractor_id)) {
                    $query->orWhere('contractor_id', $record->contractor_id);
                }

                if (filled($criteria['email'])) {
                    $query->orWhereRaw('LOWER(client_email) = ?', [$criteria['email']]);
                }

                if (filled($criteria['phone'])) {
                    $query->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(client_phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') = ?", [$criteria['phone']]);
                }

                if (filled($criteria['name'])) {
                    $query->orWhereRaw('LOWER(client_name) = ?', [$criteria['name']]);
                }
            })
            ->orderByDesc('start_datetime')
            ->limit(50)
            ->get();
    }

    private function getMatchingEvents(): Collection
    {
        $criteria = $this->getNormalizedCriteria();
        /** @var LegacyEvent $record */
        $record = $this->record;

        return Event::query()
            ->select([
                'id',
                'name',
                'status',
                'start_date',
                'end_date',
                'participant_count',
                'client_name',
                'client_phone',
                'client_email',
            ])
            ->where(function (Builder $query) use ($criteria, $record): void {
                if (filled($record->contractor_id)) {
                    $query->orWhere('contractor_id', $record->contractor_id);
                }

                if (filled($criteria['email'])) {
                    $query->orWhereRaw('LOWER(client_email) = ?', [$criteria['email']]);
                }

                if (filled($criteria['phone'])) {
                    $query->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(client_phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') = ?", [$criteria['phone']]);
                }

                if (filled($criteria['name'])) {
                    $query->orWhereRaw('LOWER(client_name) = ?', [$criteria['name']]);
                }
            })
            ->orderByDesc('start_date')
            ->limit(50)
            ->get();
    }

    private function getNormalizedCriteria(): array
    {
        /** @var LegacyEvent $record */
        $record = $this->record;

        $fallbackText = implode("\n", array_filter([
            $record->order_note,
            $record->notes,
            $record->pilot_notes,
        ]));

        $fallbackEmail = $this->extractEmailFromText($fallbackText);
        $fallbackPhone = $this->extractPhoneFromText($fallbackText);

        return [
            'email' => $this->normalizeEmail($record->client_email, true) ?? $fallbackEmail,
            'phone' => $this->normalizePhone($record->client_phone) ?? $fallbackPhone,
            'name' => $this->normalizeName($record->client_name),
        ];
    }

    private function normalizeEmail(?string $value, bool $validate = false): ?string
    {
        if (blank($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));

        if ($validate) {
            if (filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
                return null;
            }

            if ((bool) preg_match('/\.(com|net|org|pl|edu|gov|info|biz|eu)[a-z]$/i', $normalized)) {
                return null;
            }
        }

        return $normalized;
    }

    private function normalizeName(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return mb_strtolower(trim($value));
    }

    private function normalizePhone(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $digitsOnly = preg_replace('/\D+/', '', $value);

        return filled($digitsOnly) ? $digitsOnly : null;
    }

    private function extractEmailFromText(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        preg_match_all('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,10}(?![a-z])/i', strip_tags($value), $matches);

        foreach ($matches[0] ?? [] as $candidate) {
            $normalized = $this->normalizeEmail($candidate, true);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private function extractPhoneFromText(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        preg_match('/(?:\+?\d[\d\s\-()]{7,}\d)/', strip_tags($value), $matches);

        return isset($matches[0]) ? $this->normalizePhone($matches[0]) : null;
    }
}
