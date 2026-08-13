<?php

namespace App\Filament\Resources\ReservationResource\Pages;

use App\Actions\Reservations\UpsertReservationAction;
use App\Data\UpsertReservationData;
use App\Filament\Forms\ReservationFormFields;
use App\Filament\Forms\ReservationFormOptions;
use App\Filament\Resources\ReservationResource;
use App\Filament\Resources\TaskResource;
use App\Models\Reservation;
use App\Support\Tasks\TaskContextRegistry;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class EditReservation extends EditRecord
{
    protected static string $resource = ReservationResource::class;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Powiązania')
                    ->schema([
                        Forms\Components\Placeholder::make('context_links')
                            ->label('')
                            ->content(function (): HtmlString {
                                /** @var Reservation $record */
                                $record = $this->getRecord();
                                $links = TaskContextRegistry::linksForRecord($record);

                                if ($links === []) {
                                    return new HtmlString('<p class="text-sm text-gray-500">Brak powiązań do wyświetlenia.</p>');
                                }

                                $items = collect($links)
                                    ->map(function (array $link): string {
                                        $url = e($link['url']);
                                        $label = e($link['label']);

                                        return '<a href="'.$url.'" class="text-primary-600 hover:underline dark:text-primary-400">'.$label.'</a>';
                                    })
                                    ->implode(' · ');

                                return new HtmlString('<div class="text-sm">'.$items.'</div>');
                            }),
                    ])
                    ->compact()
                    ->columnSpanFull(),

                Forms\Components\Section::make('Rezerwacja')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\Select::make('event_id')
                            ->label('Impreza')
                            ->relationship('event', 'name')
                            ->searchable()
                            ->required()
                            ->live(),

                        ...ReservationFormFields::schema(new ReservationFormOptions(
                            showProgramPoint: true,
                            showSettlementCost: true,
                            showHotelNotes: true,
                            allowContractorCreate: true,
                            editingReservation: $this->getRecord(),
                        )),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('add_task')
                ->label('Dodaj zadanie')
                ->icon('heroicon-o-clipboard-document-list')
                ->url(fn (): string => TaskResource::getUrl('create').'?'.http_build_query([
                    'taskable_type' => Reservation::class,
                    'taskable_id' => $this->getRecord()->getKey(),
                ])),
            Actions\DeleteAction::make(),
        ];
    }

    /** @param array<string, mixed> $data */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Reservation $record */
        return app(UpsertReservationAction::class)(UpsertReservationData::fromForm(
            formData: $data,
            reservation: $record,
            attachmentData: $this->data,
        ));
    }
}
