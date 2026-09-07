<?php

namespace App\Filament\Forms;

use App\Enums\EventVehicleRole;
use App\Enums\VehicleStatus;
use App\Filament\Resources\ContractorResource;
use App\Models\Event;
use App\Models\EventVehicle;
use App\Models\Vehicle;
use App\Support\EventBusSeatCapacity;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * Przypisanie floty do imprezy — select pojazdu głównego (bez Repeater relationship,
 * który na ManageEventTransport powodował pętlę Livewire).
 */
final class EventVehicleFields
{
    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function mainVehicleSelect(): array
    {
        if (! Schema::hasTable('event_vehicles') || ! Schema::hasTable('vehicles')) {
            return [];
        }

        return [
            Forms\Components\Select::make('main_fleet_vehicle_id')
                ->label('Pojazd (flota)')
                ->helperText('Pojazd operacyjny z nr rejestracyjnym. Najpierw wybierz firmę transportową — nowy pojazd podczepi się pod nią. Pełna flota firmy: karta kontrahenta → Pojazdy. Autokar z cennika wybierasz osobno powyżej.')
                ->searchable()
                ->nullable()
                ->live()
                ->options(function (Get $get): array {
                    $contractorId = (int) ($get('transport_contractor_id') ?: 0) ?: null;

                    return Vehicle::query()
                        ->forContractor($contractorId)
                        ->where(function (Builder $query): void {
                            $query->where('status', VehicleStatus::Active->value)
                                ->orWhere('is_ad_hoc', true);
                        })
                        ->orderBy('registration_number')
                        ->get()
                        ->mapWithKeys(fn (Vehicle $vehicle): array => [
                            $vehicle->id => $vehicle->displayLabel(),
                        ])
                        ->all();
                })
                ->getOptionLabelUsing(function ($value): ?string {
                    if (! $value) {
                        return null;
                    }

                    return Vehicle::query()->find($value)?->displayLabel();
                })
                ->createOptionForm(VehicleFields::createOptionSchema())
                ->createOptionModalHeading('Dodaj pojazd do floty firmy')
                ->createOptionAction(function (Forms\Components\Actions\Action $action): Forms\Components\Actions\Action {
                    return $action
                        ->label('Dodaj pojazd')
                        ->disabled(fn (Get $get): bool => blank($get('transport_contractor_id')))
                        ->tooltip(fn (Get $get): ?string => blank($get('transport_contractor_id'))
                            ? 'Najpierw wybierz firmę transportową — pojazd podczepi się pod nią.'
                            : null);
                })
                ->createOptionUsing(function (array $data, Get $get): int {
                    $contractorId = (int) ($get('transport_contractor_id') ?: 0) ?: null;

                    if ($contractorId === null) {
                        Notification::make()
                            ->title('Wybierz firmę transportową')
                            ->body('Nowy pojazd musi być podpięty pod przewoźnika z imprezy.')
                            ->danger()
                            ->send();

                        throw new \RuntimeException('Brak firmy transportowej do podpięcia pojazdu.');
                    }

                    $payload = VehicleFields::payloadFromForm($data);
                    $payload['contractor_id'] = $contractorId;
                    $payload['is_ad_hoc'] = false;
                    $payload['status'] = $payload['status'] ?? VehicleStatus::Active->value;

                    $vehicle = Vehicle::query()->create($payload);

                    return $vehicle->getKey();
                })
                ->columnSpanFull(),

            Forms\Components\Placeholder::make('fleet_seat_capacity_warning')
                ->hiddenLabel()
                ->visible(fn (Get $get, ?Event $record): bool => EventBusSeatCapacity::resolveFleetMessage($get, $record) !== null)
                ->content(fn (Get $get, ?Event $record) => EventBusSeatCapacity::fleetWarningHtml($get, $record) ?? '')
                ->columnSpanFull(),

            Forms\Components\Placeholder::make('main_fleet_vehicle_media_summary')
                ->label('Załączniki pojazdu')
                ->content(function (Get $get, ?Event $record): HtmlString {
                    $vehicleId = (int) ($get('main_fleet_vehicle_id') ?: 0);
                    if ($vehicleId <= 0) {
                        return new HtmlString(
                            '<span style="color:#9ca3af;font-size:0.85rem">Wybierz pojazd albo dodaj nowy — wtedy możesz edytować dane, zdjęcia i uwagi.</span>'
                        );
                    }

                    $vehicle = Vehicle::query()->find($vehicleId);
                    if (! $vehicle) {
                        return new HtmlString('<span style="color:#9ca3af">Pojazd niedostępny.</span>');
                    }

                    $assignment = $record
                        ? EventVehicle::query()
                            ->where('event_id', $record->id)
                            ->where('vehicle_id', $vehicle->id)
                            ->first()
                        : null;

                    return new HtmlString(self::mediaSummaryHtml($vehicle, $assignment));
                })
                ->visible(fn (Get $get): bool => filled($get('main_fleet_vehicle_id')))
                ->columnSpanFull(),

            Forms\Components\Actions::make([
                Forms\Components\Actions\Action::make('edit_fleet_vehicle')
                    ->label('Edytuj pojazd')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->modalHeading('Edycja pojazdu floty')
                    ->modalDescription('Zmiany trafiają do karty pojazdu (także u kontrahenta). Rejestracja i miejsca aktualizują się na imprezie po zapisie transportu.')
                    ->modalSubmitActionLabel('Zapisz')
                    ->modalWidth('4xl')
                    ->visible(fn (Get $get): bool => filled($get('main_fleet_vehicle_id')))
                    ->fillForm(function (Get $get): array {
                        $vehicle = Vehicle::query()->find((int) $get('main_fleet_vehicle_id'));
                        if (! $vehicle) {
                            return [];
                        }

                        return [
                            'type' => $vehicle->type?->value ?? $vehicle->type,
                            'brand' => $vehicle->brand,
                            'model' => $vehicle->model,
                            'manufacture_year' => $vehicle->manufacture_year,
                            'registration_number' => $vehicle->registration_number,
                            'capacity' => $vehicle->capacity,
                            'crew_seats' => $vehicle->crew_seats,
                            'status' => $vehicle->status?->value ?? $vehicle->status,
                            'is_ad_hoc' => $vehicle->is_ad_hoc,
                            'equipment' => $vehicle->equipment ?? [],
                            'primary_image' => $vehicle->primary_image,
                            'gallery' => $vehicle->gallery ?? [],
                            'attachments' => $vehicle->attachments ?? [],
                            'notes' => $vehicle->notes,
                        ];
                    })
                    ->form(VehicleFields::editFromTransportSchema())
                    ->action(function (array $data, Get $get, Set $set): void {
                        $vehicleId = (int) ($get('main_fleet_vehicle_id') ?: 0);
                        $vehicle = Vehicle::query()->find($vehicleId);
                        if (! $vehicle) {
                            Notification::make()
                                ->title('Nie znaleziono pojazdu')
                                ->danger()
                                ->send();

                            return;
                        }

                        $contractorId = (int) ($get('transport_contractor_id') ?: 0) ?: null;
                        $payload = VehicleFields::payloadFromForm($data);

                        // Ad-hoc bez właściciela → podczep pod firmę z imprezy (jeśli jest).
                        if ($contractorId !== null && $vehicle->contractor_id === null) {
                            $payload['contractor_id'] = $contractorId;
                            $payload['is_ad_hoc'] = false;
                        }

                        $vehicle->update($payload);

                        // Odśwież etykietę selecta / placeholdery.
                        $set('main_fleet_vehicle_id', $vehicle->id);

                        Notification::make()
                            ->title('Zapisano pojazd')
                            ->success()
                            ->send();
                    }),
                Forms\Components\Actions\Action::make('open_contractor_fleet')
                    ->label('Flota u kontrahenta')
                    ->icon('heroicon-o-building-office-2')
                    ->color('gray')
                    ->url(function (Get $get): ?string {
                        $contractorId = (int) ($get('transport_contractor_id') ?: 0);
                        if ($contractorId <= 0) {
                            return null;
                        }

                        return ContractorResource::getUrl('edit', ['record' => $contractorId]);
                    })
                    ->openUrlInNewTab()
                    ->visible(fn (Get $get): bool => filled($get('transport_contractor_id'))),
            ])
                ->columnSpanFull(),
        ];
    }

    public static function mediaSummaryHtml(Vehicle $vehicle, ?EventVehicle $assignment = null): string
    {
        $parts = [];

        if (filled($vehicle->primary_image)) {
            $url = e(Storage::disk('public')->url((string) $vehicle->primary_image));
            $parts[] = '<a href="'.$url.'" target="_blank" rel="noopener" style="display:inline-block;margin:0 8px 8px 0">'
                .'<img src="'.$url.'" alt="Zdjęcie główne" style="width:72px;height:54px;object-fit:cover;border-radius:6px;border:1px solid #D3D1C7">'
                .'</a>';
        }

        foreach ((array) $vehicle->gallery as $path) {
            if (! filled($path)) {
                continue;
            }
            $url = e(Storage::disk('public')->url((string) $path));
            $parts[] = '<a href="'.$url.'" target="_blank" rel="noopener" style="display:inline-block;margin:0 8px 8px 0">'
                .'<img src="'.$url.'" alt="" style="width:56px;height:42px;object-fit:cover;border-radius:6px;border:1px solid #D3D1C7">'
                .'</a>';
        }

        $files = collect((array) $vehicle->attachments)
            ->filter()
            ->map(function (mixed $path): string {
                $url = e(Storage::disk('public')->url((string) $path));
                $name = e(basename((string) $path));

                return '<a href="'.$url.'" target="_blank" rel="noopener" style="display:inline-block;margin:0 8px 4px 0;font-size:0.8rem;color:#185FA5">📎 '.$name.'</a>';
            })
            ->implode(' ');

        $html = $parts !== []
            ? '<div style="display:flex;flex-wrap:wrap;align-items:center">'.implode('', $parts).'</div>'
            : '<div style="color:#9ca3af;font-size:0.85rem;margin-bottom:4px">Brak zdjęć.</div>';

        if ($files !== '') {
            $html .= '<div style="margin-top:6px">'.$files.'</div>';
        } else {
            $html .= '<div style="color:#9ca3af;font-size:0.85rem;margin-top:4px">Brak plików dokumentów.</div>';
        }

        if (filled($vehicle->notes)) {
            $html .= '<div style="margin-top:8px;font-size:0.85rem;color:#374151;white-space:pre-wrap">'
                .e((string) $vehicle->notes)
                .'</div>';
        }

        $pilotPhotos = $assignment?->pilotPhotoPaths() ?? [];
        if ($pilotPhotos !== []) {
            $thumbs = '';
            foreach ($pilotPhotos as $path) {
                $url = e(Storage::disk('public')->url((string) $path));
                $thumbs .= '<a href="'.$url.'" target="_blank" rel="noopener" style="display:inline-block;margin:0 8px 8px 0">'
                    .'<img src="'.$url.'" alt="" style="width:56px;height:42px;object-fit:cover;border-radius:6px;border:1px solid #D3D1C7">'
                    .'</a>';
            }
            $html .= '<div style="margin-top:10px;font-size:0.8rem;color:#6b7280">Zdjęcia z portalu pilota</div>'
                .'<div style="display:flex;flex-wrap:wrap;align-items:center;margin-top:4px">'.$thumbs.'</div>';
        }

        return $html;
    }

    /**
     * Wypełnia virtualne pole z przypisania role=main.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function hydrateMainVehicleId(Event $event, array $data): array
    {
        if (! Schema::hasTable('event_vehicles')) {
            return $data;
        }

        $mainVehicleId = EventVehicle::query()
            ->where('event_id', $event->id)
            ->where('role', EventVehicleRole::Main->value)
            ->orderBy('sort_order')
            ->value('vehicle_id');

        $data['main_fleet_vehicle_id'] = $mainVehicleId;

        return $data;
    }

    /**
     * Upsert pojazdu głównego + sync legacy vehicle_registration.
     */
    public static function persistMainVehicle(Event $event, mixed $vehicleId): void
    {
        if (! Schema::hasTable('event_vehicles')) {
            return;
        }

        $vehicleId = filled($vehicleId) ? (int) $vehicleId : null;

        if ($vehicleId === null || $vehicleId <= 0) {
            EventVehicle::query()
                ->where('event_id', $event->id)
                ->where('role', EventVehicleRole::Main->value)
                ->delete();

            return;
        }

        $existing = EventVehicle::query()
            ->where('event_id', $event->id)
            ->where('role', EventVehicleRole::Main->value)
            ->first();

        if ($existing) {
            $existing->update([
                'vehicle_id' => $vehicleId,
                'sort_order' => $existing->sort_order ?? 0,
            ]);
        } else {
            EventVehicle::query()->create([
                'event_id' => $event->id,
                'vehicle_id' => $vehicleId,
                'role' => EventVehicleRole::Main->value,
                'sort_order' => 0,
                'starts_on' => $event->start_date,
                'ends_on' => $event->end_date,
            ]);
        }

        $event->syncVehicleRegistrationFromFleet();
    }
}
