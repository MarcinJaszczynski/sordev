<?php

namespace App\Filament\Forms;

use App\Models\Contact;
use App\Models\Contractor;
use App\Services\EventOrderingPartyService;
use Filament\Forms;
use Filament\Forms\Components\Component;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;

/**
 * Podgląd + dodawanie/edycja danych kontaktowych kontrahenta bezpośrednio w formularzu
 * (Transport: firma / kierowca). Zapis idzie do Contractor/Contact, nie do Event.
 */
final class TransportContractorContactsFields
{
    /**
     * @param  callable(Contractor $contractor, Set $set, Get $get): void|null  $afterContractorCardUpdated
     * @return array<int, Component>
     */
    public static function make(
        string $contractorField,
        string $prefix,
        ?callable $afterContractorCardUpdated = null,
        bool $sidebarPreview = false,
    ): array {
        $tickField = "{$prefix}_contacts_tick";

        return [
            Forms\Components\Hidden::make($tickField)
                ->default(0)
                ->dehydrated(false),

            Forms\Components\ViewField::make("{$prefix}_contacts_preview")
                ->label('Dane kontaktowe')
                ->view('filament.components.transport-contractor-contacts-preview')
                ->viewData(function (Get $get) use ($contractorField, $tickField, $sidebarPreview): array {
                    // tick wymusza re-render po zapisie w modalu (sam contractor_id się nie zmienia)
                    $get($tickField);

                    $contractorId = (int) ($get($contractorField) ?? 0);
                    if ($contractorId <= 0) {
                        return [
                            'contractor' => null,
                            'contacts' => collect(),
                            'variant' => $sidebarPreview ? 'sidebar' : null,
                        ];
                    }

                    $contractor = Contractor::query()
                        ->with(['contacts' => fn ($query) => $query->orderBy('last_name')->orderBy('first_name')])
                        ->find($contractorId);

                    return [
                        'contractor' => $contractor,
                        'contacts' => $contractor?->contacts ?? collect(),
                        'variant' => $sidebarPreview ? 'sidebar' : null,
                    ];
                })
                ->visible(fn (Get $get): bool => filled($get($contractorField)))
                ->dehydrated(false)
                ->columnSpanFull(),

            Forms\Components\Actions::make([
                Forms\Components\Actions\Action::make("{$prefix}_edit_contractor_card")
                    ->label('Edytuj dane kontrahenta')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->modalHeading('Edytuj dane kontrahenta')
                    ->modalSubmitActionLabel('Zapisz')
                    ->fillForm(function (Get $get) use ($contractorField): array {
                        $contractor = static::resolveContractor($get, $contractorField);

                        return [
                            'name' => $contractor?->name,
                            'phone' => $contractor?->phone,
                            'email' => $contractor?->email,
                            'bank_account' => $contractor?->bank_account,
                            'nip' => $contractor?->nip,
                        ];
                    })
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->label('Nazwa')
                            ->required()
                            ->maxLength(255),
                        PhoneInput::make('phone')
                            ->label('Telefon')
                            ->nullable(),
                        Forms\Components\TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->maxLength(255)
                            ->nullable(),
                        Forms\Components\TextInput::make('nip')
                            ->label('NIP')
                            ->maxLength(20)
                            ->nullable(),
                        Forms\Components\TextInput::make('bank_account')
                            ->label('Nr konta bankowego')
                            ->maxLength(64)
                            ->nullable()
                            ->visible(fn (): bool => \Illuminate\Support\Facades\Schema::hasColumn('contractors', 'bank_account')),
                    ])
                    ->action(function (array $data, Get $get, Set $set) use ($contractorField, $tickField, $afterContractorCardUpdated): void {
                        $contractor = static::resolveContractor($get, $contractorField);
                        if (! $contractor) {
                            Notification::make()
                                ->title('Brak kontrahenta')
                                ->danger()
                                ->send();

                            return;
                        }

                        $payload = [
                            'name' => $data['name'],
                            'phone' => $data['phone'] ?? null,
                            'email' => $data['email'] ?? null,
                        ];

                        if (\Illuminate\Support\Facades\Schema::hasColumn('contractors', 'nip')) {
                            $payload['nip'] = $data['nip'] ?? null;
                        }

                        if (\Illuminate\Support\Facades\Schema::hasColumn('contractors', 'bank_account')) {
                            $payload['bank_account'] = filled($data['bank_account'] ?? null)
                                ? trim((string) $data['bank_account'])
                                : null;
                        }

                        $contractor->update($payload);

                        $contractor->refresh();
                        static::bumpTick($set, $get, $tickField);

                        if ($afterContractorCardUpdated) {
                            $afterContractorCardUpdated($contractor, $set, $get);
                        }

                        Notification::make()
                            ->title('Zapisano dane kontrahenta')
                            ->success()
                            ->send();
                    }),

                Forms\Components\Actions\Action::make("{$prefix}_add_contact")
                    ->label('Dodaj kontakt')
                    ->icon('heroicon-o-user-plus')
                    ->color('primary')
                    ->modalHeading('Dodaj kontakt do kontrahenta')
                    ->modalSubmitActionLabel('Zapisz')
                    ->form(static::contactFormSchema())
                    ->action(function (array $data, Get $get, Set $set) use ($contractorField, $tickField): void {
                        $contractorId = (int) ($get($contractorField) ?? 0);
                        if ($contractorId <= 0) {
                            Notification::make()
                                ->title('Wybierz kontrahenta')
                                ->danger()
                                ->send();

                            return;
                        }

                        app(EventOrderingPartyService::class)->createContact($data, $contractorId);
                        static::bumpTick($set, $get, $tickField);

                        Notification::make()
                            ->title('Dodano kontakt')
                            ->success()
                            ->send();
                    }),

                Forms\Components\Actions\Action::make("{$prefix}_edit_contact")
                    ->label('Edytuj kontakt')
                    ->icon('heroicon-o-user')
                    ->color('gray')
                    ->modalHeading('Edytuj kontakt')
                    ->modalSubmitActionLabel('Zapisz')
                    ->visible(function (Get $get) use ($contractorField): bool {
                        $contractor = static::resolveContractor($get, $contractorField);

                        return (bool) $contractor?->contacts()->exists();
                    })
                    ->fillForm(function (Get $get) use ($contractorField): array {
                        $contractor = static::resolveContractor($get, $contractorField);
                        $first = $contractor?->contacts()->orderBy('last_name')->orderBy('first_name')->first();

                        if (! $first) {
                            return [];
                        }

                        return [
                            'contact_id' => $first->id,
                            'first_name' => $first->first_name,
                            'last_name' => $first->last_name,
                            'phone' => $first->phone,
                            'email' => $first->email,
                        ];
                    })
                    ->form(function (Get $get) use ($contractorField): array {
                        $contractorId = (int) ($get($contractorField) ?? 0);

                        return static::contactFormSchema(
                            includeContactSelect: true,
                            contractorId: $contractorId,
                        );
                    })
                    ->action(function (array $data, Get $get, Set $set) use ($contractorField, $tickField): void {
                        $contractorId = (int) ($get($contractorField) ?? 0);
                        $contactId = (int) ($data['contact_id'] ?? 0);

                        $contact = Contact::query()
                            ->whereKey($contactId)
                            ->whereHas('contractors', fn ($query) => $query->whereKey($contractorId))
                            ->first();

                        if (! $contact) {
                            Notification::make()
                                ->title('Nie znaleziono kontaktu')
                                ->danger()
                                ->send();

                            return;
                        }

                        $contact->update([
                            'first_name' => $data['first_name'],
                            'last_name' => $data['last_name'],
                            'phone' => $data['phone'] ?? null,
                            'email' => $data['email'] ?? null,
                        ]);

                        static::bumpTick($set, $get, $tickField);

                        Notification::make()
                            ->title('Zapisano kontakt')
                            ->success()
                            ->send();
                    }),
            ])
                ->visible(fn (Get $get): bool => filled($get($contractorField)))
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, Component>
     */
    private static function contactFormSchema(bool $includeContactSelect = false, int $contractorId = 0): array
    {
        $fields = [];

        if ($includeContactSelect) {
            $fields[] = Forms\Components\Select::make('contact_id')
                ->label('Kontakt')
                ->required()
                ->live()
                ->options(function () use ($contractorId): array {
                    if ($contractorId <= 0) {
                        return [];
                    }

                    return Contact::query()
                        ->whereHas('contractors', fn ($query) => $query->whereKey($contractorId))
                        ->orderBy('last_name')
                        ->orderBy('first_name')
                        ->get()
                        ->mapWithKeys(fn (Contact $contact): array => [
                            $contact->id => $contact->displayName(),
                        ])
                        ->all();
                })
                ->afterStateUpdated(function ($state, Set $set): void {
                    $contact = filled($state) ? Contact::query()->find((int) $state) : null;
                    $set('first_name', $contact?->first_name);
                    $set('last_name', $contact?->last_name);
                    $set('phone', $contact?->phone);
                    $set('email', $contact?->email);
                })
                ->columnSpanFull();
        }

        $fields[] = Forms\Components\Grid::make(2)->schema([
            Forms\Components\TextInput::make('first_name')
                ->label('Imię')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('last_name')
                ->label('Nazwisko')
                ->required()
                ->maxLength(255),
            PhoneInput::make('phone')
                ->label('Telefon')
                ->nullable(),
            Forms\Components\TextInput::make('email')
                ->label('E-mail')
                ->email()
                ->maxLength(255)
                ->nullable(),
        ]);

        return $fields;
    }

    private static function resolveContractor(Get $get, string $contractorField): ?Contractor
    {
        $contractorId = (int) ($get($contractorField) ?? 0);

        if ($contractorId <= 0) {
            return null;
        }

        return Contractor::query()->find($contractorId);
    }

    private static function bumpTick(Set $set, Get $get, string $tickField): void
    {
        $set($tickField, ((int) ($get($tickField) ?? 0)) + 1);
    }
}
