<?php

namespace App\Filament\Forms;

use App\Filament\Resources\EventResource\Traits\SearchContractorTrait;
use App\Models\Contact;
use App\Models\Contractor;
use App\Services\ContactContractorLinkService;
use App\Services\EventOrderingPartyService;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;

class EventOrderingPartyFields
{
    use SearchContractorTrait;

    public static function orderingPartiesRepeater(): Forms\Components\Repeater
    {
        $service = app(EventOrderingPartyService::class);

        return Forms\Components\Repeater::make('ordering_parties')
            ->label('Zamawiający')
            ->schema([
                Forms\Components\Select::make('contact_id')
                    ->label('Osoba kontaktowa')
                    ->searchable()
                    ->preload()
                    ->options(fn (): array => $service->contactOptions())
                    ->getSearchResultsUsing(fn (string $search): array => $service->contactOptions($search))
                    ->getOptionLabelUsing(function ($value): ?string {
                        if (! $value) {
                            return null;
                        }

                        $contact = Contact::find($value);

                        return $contact ? app(EventOrderingPartyService::class)->formatContactLabel($contact) : null;
                    })
                    ->createOptionForm(static::contactCreateOptionSchema())
                    ->createOptionUsing(function (array $data, Get $get): int {
                        $contractorId = filled($data['contractor_id'] ?? null)
                            ? (int) $data['contractor_id']
                            : (filled($get('contractor_id')) ? (int) $get('contractor_id') : null);

                        return app(EventOrderingPartyService::class)->createContact($data, $contractorId);
                    })
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                        if (! $state) {
                            return;
                        }

                        $contact = Contact::with('contractors')->find($state);

                        if (! $contact) {
                            return;
                        }

                        if (blank($get('contractor_id')) && $contact->contractors->count() === 1) {
                            $set('contractor_id', (string) $contact->contractors->first()->id);
                        } elseif (filled($get('contractor_id'))) {
                            app(ContactContractorLinkService::class)->link(
                                (int) $contact->id,
                                (int) $get('contractor_id'),
                            );
                        }

                        static::applyPartyRowToClientFields($get('../../ordering_parties'), $set);
                    })
                    ->helperText('Wyszukaj osobę po imieniu, nazwisku, e-mailu lub telefonie. Możesz dodać nową osobę.')
                    ->columnSpanFull(),

                Forms\Components\Select::make('contractor_id')
                    ->label('Firma / instytucja')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->options(fn (Get $get): array => $service->contractorOptionsForContact(
                        filled($get('contact_id')) ? (int) $get('contact_id') : null,
                    ))
                    ->getSearchResultsUsing(fn (string $search, Get $get): array => $service->contractorOptionsForContact(
                        filled($get('contact_id')) ? (int) $get('contact_id') : null,
                        $search,
                    ))
                    ->getOptionLabelUsing(function ($value): ?string {
                        if (! $value) {
                            return null;
                        }

                        $contractor = Contractor::find($value);

                        return $contractor
                            ? app(EventOrderingPartyService::class)->formatContractorLabel($contractor)
                            : null;
                    })
                    ->createOptionForm(static::contractorQuickCreateSchema())
                    ->createOptionUsing(function (array $data, Get $get): int {
                        $contactId = filled($get('contact_id')) ? (int) $get('contact_id') : null;

                        return static::createContractorFromFormData($data, $contactId);
                    })
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                        if (! $state) {
                            return;
                        }

                        $contactId = filled($get('contact_id')) ? (int) $get('contact_id') : null;

                        if ($contactId) {
                            app(ContactContractorLinkService::class)->link($contactId, (int) $state);
                        }

                        static::applyPartyRowToClientFields($get('../../ordering_parties'), $set);
                    })
                    ->helperText('Ta sama osoba może zamawiać z różnych firm — wybierz właściwą instytucję.')
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('department_label')
                    ->label('Dział / oddział / szkoła')
                    ->maxLength(255)
                    ->placeholder('np. SP nr 3, dział marketingu')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, Set $set, Get $get) => static::applyPartyRowToClientFields($get('../../ordering_parties'), $set))
                    ->columnSpanFull(),

                Forms\Components\ViewField::make('party_details_preview')
                    ->label('Dane kontaktowe')
                    ->view('filament.components.ordering-party-contact-preview')
                    ->viewData(function (Get $get): array {
                        $contact = filled($get('contact_id')) ? Contact::find($get('contact_id')) : null;
                        $contractor = filled($get('contractor_id')) ? Contractor::find($get('contractor_id')) : null;

                        return [
                            'contact' => $contact,
                            'contractor' => $contractor,
                        ];
                    })
                    ->visible(fn (Get $get): bool => filled($get('contractor_id')) || filled($get('contact_id')))
                    ->dehydrated(false)
                    ->columnSpanFull(),
            ])
            ->minItems(1)
            ->defaultItems(1)
            ->addActionLabel('Dodaj zamawiającego')
            ->reorderable()
            ->collapsible()
            ->itemLabel(fn (array $state): string => app(EventOrderingPartyService::class)->formatPartyLabel(
                filled($state['contact_id'] ?? null) ? (int) $state['contact_id'] : null,
                filled($state['contractor_id'] ?? null) ? (int) $state['contractor_id'] : null,
                $state['department_label'] ?? null,
            ))
            ->live()
            ->afterStateUpdated(fn ($state, Set $set) => static::applyPartyRowToClientFields($state, $set))
            ->helperText('Dodaj jedną lub więcej par: osoba kontaktowa + firma. Pierwszy wpis ustawia główne dane zamawiającego poniżej.')
            ->columnSpanFull();
    }

    /**
     * @deprecated Użyj orderingPartiesRepeater()
     */
    public static function orderingContractorsSelect(): Forms\Components\Repeater
    {
        return static::orderingPartiesRepeater();
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function contactCreateOptionSchema(): array
    {
        return [
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('first_name')
                    ->label('Imię')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('last_name')
                    ->label('Nazwisko')
                    ->required()
                    ->maxLength(255),
                PhoneInput::make('phone')
                    ->label('Telefon'),
                Forms\Components\TextInput::make('email')
                    ->label('E-mail')
                    ->email()
                    ->maxLength(255),
                Forms\Components\Select::make('contractor_id')
                    ->label('Powiąż od razu z firmą (opcjonalnie)')
                    ->searchable()
                    ->options(fn (): array => static::getContractorOptions()->toArray())
                    ->getSearchResultsUsing(fn (string $search): array => static::getContractorOptions($search)->toArray())
                    ->columnSpanFull(),
            ]),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function contractorQuickCreateSchema(): array
    {
        return [
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nazwa klienta / firmy')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                PhoneInput::make('phone')
                    ->label('Telefon'),
                Forms\Components\TextInput::make('email')
                    ->label('E-mail')
                    ->email()
                    ->maxLength(255),
                Forms\Components\TextInput::make('nip')
                    ->label('NIP')
                    ->maxLength(20),
                Forms\Components\TextInput::make('city')
                    ->label('Miasto')
                    ->maxLength(255),
                Forms\Components\TextInput::make('street')
                    ->label('Ulica')
                    ->maxLength(255),
                Forms\Components\TextInput::make('house_number')
                    ->label('Nr domu')
                    ->maxLength(20),
                Forms\Components\TextInput::make('postal_code')
                    ->label('Kod pocztowy')
                    ->maxLength(20),
                \FilamentTiptapEditor\TiptapEditor::make('office_notes')
                    ->columnSpanFull(),
            ]),
        ];
    }

    /**
     * @deprecated Użyj contractorQuickCreateSchema()
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function contractorCreateOptionSchema(): array
    {
        return static::contractorQuickCreateSchema();
    }

    public static function createContractorFromFormData(array $data, ?int $contactId = null): int
    {
        $contractorId = Contractor::create([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'nip' => $data['nip'] ?? null,
            'street' => $data['street'] ?? null,
            'house_number' => $data['house_number'] ?? null,
            'city' => $data['city'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'office_notes' => $data['office_notes'] ?? null,
            'status' => 'active',
        ])->getKey();

        app(ContactContractorLinkService::class)->link($contactId, (int) $contractorId);

        return (int) $contractorId;
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $parties
     */
    public static function applyPartyRowToClientFields(mixed $parties, callable $set): void
    {
        $attributes = app(EventOrderingPartyService::class)->primaryClientAttributes(
            is_array($parties) ? $parties : null,
        );

        if ($attributes === []) {
            return;
        }

        foreach ($attributes as $field => $value) {
            if (in_array($field, ['client_name', 'client_email', 'client_phone', 'contractor_id'], true)) {
                $set($field, $value);
            }
        }
    }

    public static function applyPrimaryOrderingPartyToClientFields(mixed $state, callable $set): void
    {
        if (is_array($state) && array_is_list($state) && isset($state[0]['contractor_id'])) {
            static::applyPartyRowToClientFields($state, $set);

            return;
        }

        $ids = collect(is_array($state) ? $state : [$state])->filter()->values();
        $firstId = $ids->first();

        if (! $firstId) {
            return;
        }

        $contractor = Contractor::find($firstId);

        if (! $contractor) {
            return;
        }

        $contractorData = static::mapContractorToEventData($contractor);
        $set('client_name', $contractorData['client_name']);
        $set('client_email', $contractorData['client_email']);
        $set('client_phone', $contractorData['client_phone']);
    }
}
