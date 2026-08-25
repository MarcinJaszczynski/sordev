<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Pilot\Pages\PilotHotelPlanPage;
use App\Filament\Resources\ContractorResource;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventOperationsSubNavigation;
use App\Filament\Resources\EventResource\Concerns\InteractsWithEventRecord;
use App\Models\Currency;
use App\Models\EventHotelStay;
use App\Models\EventProgramPoint;
use App\Services\EventHotelOccupancyService;
use App\Support\ContractorContactDetails;
use App\Support\CurrencyAmountDisplay;
use App\Support\EventHotelPlanFormatting;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Auth;

class EventHotelPlanning extends Page
{
    use HasEventOperationsSubNavigation;
    use InteractsWithEventRecord;

    protected static string $resource = EventResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.event-hotel-planning';

    protected static ?string $navigationLabel = 'Hotele';

    protected static ?string $title = 'Hotele';

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    /** @var 'overview'|'planning'|'program'|'services' */
    public string $hotelTab = 'overview';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->record->load(['eventTemplate', 'hotelStays.contractor', 'hotelStays.contractorLocation']);
    }

    public function setHotelTab(string $tab): void
    {
        if (! in_array($tab, ['overview', 'planning', 'program', 'services'], true)) {
            return;
        }

        $this->hotelTab = $tab;
    }

    /**
     * @return array{
     *   participants: int,
     *   gratis: int,
     *   staff: int,
     *   drivers: int,
     *   pilot: int,
     *   pilot_staff: int,
     *   required_beds_per_night: int,
     *   stays: list<array<string, mixed>>
     * }
     */
    public function occupancySummary(): array
    {
        return app(EventHotelOccupancyService::class)->forEvent($this->record);
    }

    /**
     * @return array{
     *   nights: int,
     *   lodging_total_label: string,
     *   night_hotels: list<array<string, mixed>>
     * }
     */
    public function hotelHeaderMeta(): array
    {
        $this->record->loadMissing([
            'hotelStays.contractor',
            'hotelStays.contractorLocation',
            'hotelStays.roomLines',
        ]);

        $stays = $this->record->hotelStays->sortBy('day')->values();
        $currencies = Currency::query()->pluck('symbol', 'id');
        $staysPayload = $stays->map(fn (EventHotelStay $stay): array => $this->stayToFormattingPayload($stay))->all();
        $peoplePerNight = (int) ($this->occupancySummary()['required_beds_per_night'] ?? 0);

        $lodgingTotal = EventHotelPlanFormatting::eventTotalDisplay(
            $staysPayload,
            $this->record->hotel_pricing_mode ?? 'lines',
            $this->record->hotel_flat_stay_amount !== null
                ? (float) $this->record->hotel_flat_stay_amount
                : null,
            $this->record->hotel_flat_stay_currency_id,
            (bool) ($this->record->hotel_flat_stay_convert_to_pln ?? true),
            $currencies,
            $peoplePerNight,
        );

        $nightHotels = [];
        foreach ($stays as $stay) {
            $contractor = $stay->contractor;
            $meta = ContractorContactDetails::operationalMeta($contractor, $stay->contractorLocation);
            $dayDate = $this->record->dateForProgramDay((int) $stay->day);

            $nightHotels[] = [
                'day' => (int) $stay->day,
                'date' => $dayDate?->format('d.m.Y'),
                'name' => $contractor?->displayLabel() ?? $contractor?->name,
                'address' => $meta['address'] ?? null,
                'phone' => $meta['phone'] ?? null,
                'email' => $meta['email'] ?? null,
                'edit_url' => $contractor
                    ? ContractorResource::getUrl('edit', ['record' => $contractor])
                    : null,
            ];
        }

        return [
            'nights' => $stays->count(),
            'lodging_total_label' => $lodgingTotal,
            'night_hotels' => $nightHotels,
        ];
    }

    /**
     * Punkty programu powiązane z hotelami (noclegi + usługi hotelowe).
     *
     * @return list<array<string, mixed>>
     */
    public function hotelProgramOverviewRows(): array
    {
        $this->record->loadMissing([]);

        $points = $this->record->hotelProgramPoints()
            ->with(['contractor', 'currency'])
            ->get()
            ->concat(
                $this->record->hotelServiceProgramPoints()
                    ->with(['contractor', 'currency'])
                    ->get()
            )
            ->sortBy([
                ['day', 'asc'],
                ['order', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        return $points->map(function (EventProgramPoint $point): array {
            $total = (float) ($point->total_price ?? 0);
            if ($total <= 0) {
                $total = round(
                    (float) ($point->unit_price ?? 0) * max(1, (int) ($point->quantity ?? 1)),
                    2
                );
            }

            $start = $point->start_time;
            $timeLabel = null;
            if ($start) {
                $timeLabel = is_string($start)
                    ? substr($start, 0, 5)
                    : $start->format('H:i');
            }

            return [
                'id' => $point->id,
                'day' => (int) ($point->day ?? 0),
                'time' => $timeLabel,
                'name' => $point->name ?: '—',
                'kind' => (bool) ($point->is_hotel_service ?? false) ? 'Usługa' : 'Nocleg',
                'hotel' => $point->contractor?->name,
                'price_label' => CurrencyAmountDisplay::format(
                    $total,
                    $point->currency,
                    (bool) ($point->convert_to_pln ?? true),
                ),
            ];
        })->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function stayToFormattingPayload(EventHotelStay $stay): array
    {
        return [
            'pricing_mode' => $stay->pricing_mode ?? 'lines',
            'flat_amount' => $stay->flat_amount,
            'flat_currency_id' => $stay->flat_currency_id,
            'flat_convert_to_pln' => $stay->flat_convert_to_pln,
            'room_lines' => $stay->roomLines->map(static fn ($line): array => [
                'hotel_room_id' => $line->hotel_room_id,
                'label' => $line->label,
                'quantity' => $line->quantity,
                'people_count' => $line->people_count,
                'unit_price' => $line->unit_price,
                'price_basis' => $line->price_basis,
                'currency_id' => $line->currency_id,
                'convert_to_pln' => $line->convert_to_pln,
            ])->all(),
        ];
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        $actions[] = Action::make('pdf_hotel')
            ->label('Pakiet hotelu')
            ->icon('heroicon-o-document-arrow-down')
            ->color('gray')
            ->tooltip('Załączniki dodasz w zakładce „Dokumenty”: zaznacz pakiety PDF i status „Zaakceptowany”.')
            ->url(fn () => route('admin.events.pdf', ['event' => $this->record->id, 'audience' => 'hotel']))
            ->openUrlInNewTab();

        $actions[] = Action::make('pdf_hotel_agendas')
            ->label('Agendy hotelowe (ZIP)')
            ->icon('heroicon-o-building-office-2')
            ->color('gray')
            ->tooltip('Osobny PDF „Agenda dla hotelu” dla każdego obiektu z planu noclegów.')
            ->url(fn () => route('admin.events.pdf', ['event' => $this->record->id, 'audience' => 'hotel_agendas']))
            ->openUrlInNewTab();

        if (Auth::user()?->hasRole(['admin', 'super_admin'])) {
            $actions[] = Action::make('pilotHotelPreview')
                ->label('Podgląd w portalu pilota')
                ->icon('heroicon-o-building-office-2')
                ->color('gray')
                ->url(PilotHotelPlanPage::urlFor($this->record))
                ->openUrlInNewTab();
        }

        return $actions;
    }
}
