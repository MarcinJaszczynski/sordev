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

    /**
     * Lookup klienta (karta jak przy zakładaniu) + ukryte client_* + zwinięty repeater.
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function clientLookupFields(): array
    {
        return [
            Forms\Components\View::make('filament.components.event-client-lookup-wrapper')
                ->viewData(function (?\Illuminate\Database\Eloquent\Model $record): array {
                    $initialSelected = null;
                    $initialAdditional = [];
                    $primaryContractorId = null;
                    $wireKey = 'event-client-lookup-create';

                    if ($record instanceof \App\Models\Event && $record->exists) {
                        $lookup = app(\App\Services\ClientLookupService::class);
                        $initialSelected = $lookup->selectedFromEvent($record);
                        $initialAdditional = $lookup->additionalSelectedFromEvent($record);
                        $primaryContractorId = filled($initialSelected['contractor_id'] ?? null)
                            ? (int) $initialSelected['contractor_id']
                            : ($record->contractor_id ? (int) $record->contractor_id : null);
                        $wireKey = 'event-client-lookup-edit-'.$record->getKey();
                    }

                    return [
                        'initialSelected' => $initialSelected,
                        'initialAdditional' => $initialAdditional,
                        'primaryContractorId' => $primaryContractorId,
                        'wireKey' => $wireKey,
                        'additionalWireKey' => $wireKey.'-additional',
                    ];
                })
                ->columnSpanFull(),
            Forms\Components\Hidden::make('client_name')
                ->required()
                ->dehydrated(),
            Forms\Components\Hidden::make('client_email')
                ->dehydrated(),
            Forms\Components\Hidden::make('client_phone')
                ->dehydrated(),
            // Stan formularza (dehydrated) — UI edycji jest w Livewire powyżej.
            static::orderingPartiesRepeater()
                ->hidden()
                ->dehydrated()
                ->minItems(0)
                ->defaultItems(0)
                ->columnSpanFull(),
        ];
    }

    public static function orderingPartiesRepeater(): Forms\Components\Repeater
    {
        $service = app(EventOrderingPartyService::class);

        return Forms\Components\Repeater::make('ordering_parties')
            ->label('Zamawiający')
            ->schema([
                Forms\Components\Placeholder::make('party_role_badge')
                    ->label('')
                    ->content(function (Get $get, Forms\Components\Component $component): string {
                        return static::isPrimaryOrderingPartyItem($get, $component)
                            ? 'Główny zamawiający — te dane trafiają na kartę klienta i do pól imprezy.'
                            : 'Dodatkowy kontakt — nie zmienia karty głównego zamawiającego.';
                    })
                    ->columnSpanFull(),

                Forms\Components\Select::make('contractor_id')
                    ->label('Firma / instytucja')
                    ->searchable()
                    ->preload()
                    ->required()
                    // Szukamy w całej bazie; contactId tylko priorytetyzuje już powiązane firmy.
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

                        return static::createContractorFromFormData(
                            $data,
                            $contactId,
                            \App\Models\ContractorType::clientTypeNames(),
                        );
                    })
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set, Get $get, Forms\Components\Component $component): void {
                        if (! $state) {
                            return;
                        }

                        $contactId = filled($get('contact_id')) ? (int) $get('contact_id') : null;

                        if ($contactId) {
                            app(ContactContractorLinkService::class)->link($contactId, (int) $state);
                        }

                        if (static::isPrimaryOrderingPartyItem($get, $component)) {
                            static::applyPartyRowToClientFields($get('../../ordering_parties'), $set);
                        }
                    })
                    ->helperText('Wyszukaj firmę z bazy (po nazwie, NIP, mieście, telefonie, e-mailu) albo dodaj nową.')
                    ->columnSpanFull(),

                Forms\Components\Select::make('contact_id')
                    ->label('Osoba kontaktowa')
                    ->searchable()
                    ->preload()
                    ->options(fn (Get $get): array => $service->contactOptionsForContractor(
                        filled($get('contractor_id')) ? (int) $get('contractor_id') : null,
                    ))
                    ->getSearchResultsUsing(fn (string $search, Get $get): array => $service->contactOptionsForContractor(
                        filled($get('contractor_id')) ? (int) $get('contractor_id') : null,
                        $search,
                    ))
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
                    ->afterStateUpdated(function (?string $state, Set $set, Get $get, Forms\Components\Component $component): void {
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

                        if (static::isPrimaryOrderingPartyItem($get, $component)) {
                            static::applyPartyRowToClientFields($get('../../ordering_parties'), $set);
                        }
                    })
                    ->helperText('Po wyborze firmy lista podpowiada jej kontakty. Dalej możesz szukać w całej bazie.')
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('department_label')
                    ->label('Dział / oddział / szkoła')
                    ->maxLength(255)
                    ->placeholder('Wpisz dział, oddział lub szkołę')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, Set $set, Get $get, Forms\Components\Component $component): void {
                        if (static::isPrimaryOrderingPartyItem($get, $component)) {
                            static::applyPartyRowToClientFields($get('../../ordering_parties'), $set);
                        }
                    })
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('notes')
                    ->label('Rola / notatka')
                    ->rows(2)
                    ->maxLength(1000)
                    ->placeholder('np. rodzic odpowiedzialny za rozliczenie, nauczyciel jadący na wycieczkę')
                    ->helperText('Widoczne tylko wewnętrznie — pomaga rozróżnić kontakty przy tej samej firmie.')
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
            ->addActionLabel('Dodaj dodatkowy kontakt')
            ->reorderable()
            ->collapsible()
            ->itemLabel(fn (array $state): string => app(EventOrderingPartyService::class)->formatPartyItemHeading(
                filled($state['contact_id'] ?? null) ? (int) $state['contact_id'] : null,
                filled($state['contractor_id'] ?? null) ? (int) $state['contractor_id'] : null,
                $state['department_label'] ?? null,
                $state['notes'] ?? null,
            ))
            ->live()
            ->afterStateUpdated(fn ($state, Set $set) => static::applyPartyRowToClientFields($state, $set))
            ->helperText('Pierwsza pozycja = główny zamawiający. Kolejne = dodatkowe kontakty. Kolejność możesz zmieniać przeciąganiem.')
            ->columnSpanFull();
    }

    /**
     * Czy bieżący wiersz repeatera jest pierwszym (głównym) zamawiającym.
     */
    public static function isPrimaryOrderingPartyItem(Get $get, Forms\Components\Component $component): bool
    {
        $itemPath = $component->getContainer()->getStatePath();
        $uuid = str_contains($itemPath, '.')
            ? substr($itemPath, (int) strrpos($itemPath, '.') + 1)
            : $itemPath;

        $parties = $get('../../ordering_parties');

        if (! is_array($parties) || $parties === []) {
            return true;
        }

        $firstKey = array_key_first($parties);

        return $firstKey === $uuid;
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

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $typeNames
     */
    public static function createContractorFromFormData(array $data, ?int $contactId = null, array $typeNames = []): int
    {
        return app(EventOrderingPartyService::class)->createContractorFromFormData($data, $contactId, $typeNames);
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
