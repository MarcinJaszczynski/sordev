<?php

namespace App\Filament\Forms;

use App\Models\Event;
use App\Models\TfgDictionaryItem;
use App\Services\ContractTfgSetupService;
use Closure;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;

class ContractTfgForm
{
    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function schema(bool $collapsed = true, ?Closure $resolveEvent = null, bool $includeParticipantFields = true): array
    {
        $fields = $includeParticipantFields
            ? ContractParticipantFields::schema($collapsed)
            : [];

        $section = Forms\Components\Section::make('Parametry UFG / TFG')
            ->description('Wymagane do raportowania w UFG. Domyślnie uzupełniane z danych imprezy (liczba uczestników, terminy, transport).')
            ->columns(2)
            ->schema([
                Forms\Components\Select::make('subject_code')
                    ->label('Przedmiot umowy')
                    ->options(fn () => TfgDictionaryItem::optionsFor(TfgDictionaryItem::TYPE_SUBJECT))
                    ->default('IT')
                    ->searchable()
                    ->required(),

                Forms\Components\Select::make('payment_method_code')
                    ->label('Sposób wpłat (UFG)')
                    ->options(fn () => TfgDictionaryItem::optionsFor(TfgDictionaryItem::TYPE_PAYMENT_METHOD))
                    ->default('WPLATAPRZED')
                    ->searchable()
                    ->required(),

                Forms\Components\TextInput::make('reservation_number')
                    ->label('Numer rezerwacji')
                    ->maxLength(255)
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('tfg_travelers_count')
                    ->label('Liczba podróżnych (wariant)')
                    ->numeric()
                    ->minValue(1)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                        if (filled($state)) {
                            $set('participant_count', max(1, (int) $state));
                        }
                    })
                    ->required(),

                Forms\Components\DatePicker::make('tfg_starts_at')
                    ->label('Termin od')
                    ->native(false)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, mixed $state) => filled($state) ? $set('event_start_date', $state) : null),

                Forms\Components\DatePicker::make('tfg_ends_at')
                    ->label('Termin do')
                    ->native(false)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, mixed $state) => filled($state) ? $set('event_end_date', $state) : null),

                Forms\Components\Select::make('tfg_scope_type')
                    ->label('Zakres lokalizacji')
                    ->options(fn () => TfgDictionaryItem::optionsFor(TfgDictionaryItem::TYPE_SCOPE))
                    ->default('PLISAS'),

                Forms\Components\Select::make('tfg_country_code')
                    ->label('Kraj')
                    ->options(fn () => TfgDictionaryItem::optionsFor(TfgDictionaryItem::TYPE_COUNTRY))
                    ->default('PL')
                    ->searchable(),

                Forms\Components\TextInput::make('tfg_locality')
                    ->label('Miejscowość')
                    ->maxLength(255),

                Forms\Components\Select::make('tfg_transport_code')
                    ->label('Transport')
                    ->options(fn () => TfgDictionaryItem::optionsFor(TfgDictionaryItem::TYPE_TRANSPORT))
                    ->default('NLOT')
                    ->live()
                    ->required(),

                Forms\Components\TagsInput::make('tfg_icao_codes')
                    ->label('Kody ICAO (lot)')
                    ->placeholder('np. EPWA')
                    ->visible(fn (Get $get) => TfgDictionaryItem::requiresIcao((string) $get('tfg_transport_code')))
                    ->columnSpanFull(),
            ]);

        if ($resolveEvent !== null) {
            $section->headerActions([
                Forms\Components\Actions\Action::make('pull_tfg_from_event')
                    ->label('Uzupełnij z imprezy')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(function (Set $set) use ($resolveEvent): void {
                        $event = $resolveEvent();

                        if (! $event instanceof Event) {
                            return;
                        }

                        $payload = app(ContractTfgSetupService::class)->fillFormFromEvent($event);

                        foreach ($payload as $key => $value) {
                            $set($key, $value);
                        }
                    }),
            ]);
        }

        if ($collapsed) {
            $section->collapsed();
        }

        return [...$fields, $section];
    }

    public static function syncMainFieldsToTfg(Set $set, Get $get): void
    {
        if (filled($get('participant_count'))) {
            $set('tfg_travelers_count', max(1, (int) $get('participant_count')));
        }

        if (filled($get('event_start_date'))) {
            $set('tfg_starts_at', $get('event_start_date'));
        }

        if (filled($get('event_end_date'))) {
            $set('tfg_ends_at', $get('event_end_date'));
        }
    }
}
