<?php

namespace App\Filament\Forms;

use App\Filament\Resources\EventResource\Traits\SearchContractorTrait;
use App\Models\Contractor;
use Filament\Forms;
use Filament\Forms\Set;

class ContractOrderingPartyFields
{
    use SearchContractorTrait;

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function schema(): array
    {
        return [
            Forms\Components\Repeater::make('ordering_parties')
                ->label('Zamawiający')
                ->schema([
                    Forms\Components\Select::make('contractor_id')
                        ->label('Kontrahent z bazy')
                        ->searchable()
                        ->preload()
                        ->options(fn (): array => static::getContractorOptions()->toArray())
                        ->getSearchResultsUsing(fn (string $search): array => static::getContractorOptions($search)->toArray())
                        ->getOptionLabelUsing(function ($value): ?string {
                            if (! $value) {
                                return null;
                            }

                            $contractor = Contractor::find($value);

                            return $contractor
                                ? $contractor->name.' ('.($contractor->city ?? 'brak miasta').')'
                                : null;
                        })
                        ->live()
                        ->afterStateUpdated(function (?string $state, Set $set): void {
                            if (! $state) {
                                return;
                            }

                            $contractor = Contractor::find($state);

                            if (! $contractor) {
                                return;
                            }

                            $mainContact = Contractor::hasContactPivotTable()
                                ? $contractor->contacts()->first()
                                : null;

                            $set('name', $contractor->name);
                            $set('email', $mainContact?->email ?? $contractor->email);
                            $set('phone', $mainContact?->phone ?? $contractor->phone);
                            $set('nip', $contractor->nip);
                            $set('street', $contractor->street);
                            $set('house_number', $contractor->house_number);
                            $set('city', $contractor->city);
                            $set('postal_code', $contractor->postal_code);
                        })
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('name')
                        ->label('Nazwa zamawiającego')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('email')
                        ->label('E-mail')
                        ->email()
                        ->maxLength(255),

                    PhoneInput::make('phone')
                        ->label('Telefon'),

                    Forms\Components\TextInput::make('nip')
                        ->label('NIP')
                        ->maxLength(20),

                    Forms\Components\TextInput::make('street')
                        ->label('Ulica')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('house_number')
                        ->label('Nr domu')
                        ->maxLength(20),

                    Forms\Components\TextInput::make('city')
                        ->label('Miasto')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('postal_code')
                        ->label('Kod pocztowy')
                        ->maxLength(20),

                    Forms\Components\Textarea::make('notes')
                        ->label('Uwagi do zamawiającego')
                        ->rows(2)
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->minItems(1)
                ->defaultItems(1)
                ->addActionLabel('Dodaj zamawiającego')
                ->reorderable()
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => filled($state['name'] ?? null) ? (string) $state['name'] : 'Nowy zamawiający')
                ->helperText('Możesz dodać dwóch lub więcej zamawiających. Pierwszy na liście jest traktowany jako główny kontakt na umowie.')
                ->columnSpanFull(),

            Forms\Components\Textarea::make('ordering_party_notes')
                ->label('Dodatkowe uwagi do zamawiających')
                ->rows(3)
                ->columnSpanFull(),
        ];
    }
}
