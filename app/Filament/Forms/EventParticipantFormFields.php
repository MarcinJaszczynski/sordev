<?php

namespace App\Filament\Forms;

use App\Models\Event;
use App\Services\EventPriceSummaryService;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Facades\Schema;

class EventParticipantFormFields
{
    /**
     * Kanoniczne pola uczestnika imprezy (lista + wpłaty + portal).
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function identityFields(): array
    {
        $fields = [
            Forms\Components\TextInput::make('first_name')
                ->label('Imię')
                ->maxLength(120)
                ->requiredWithout('last_name'),

            Forms\Components\TextInput::make('last_name')
                ->label('Nazwisko')
                ->maxLength(120),

            Forms\Components\DatePicker::make('birth_date')
                ->label('Data urodzenia')
                ->native(false)
                ->displayFormat('d.m.Y'),

            Forms\Components\TextInput::make('pesel')
                ->label('PESEL')
                ->maxLength(11),

            Forms\Components\TextInput::make('email')
                ->label('E-mail')
                ->email()
                ->maxLength(255),

            Forms\Components\TextInput::make('phone')
                ->label('Telefon')
                ->maxLength(50),

            Forms\Components\TextInput::make('booking_reference')
                ->label('Nr rezerwacji')
                ->maxLength(120),

            Forms\Components\TextInput::make('diet')
                ->label('Dieta')
                ->maxLength(255)
                ->placeholder('Wpisz dietę'),
        ];

        if (Schema::hasColumn('event_participants', 'parent_consent_at')) {
            $fields[] = Forms\Components\Toggle::make('parent_consent')
                ->label('Zgoda rodzica / opiekuna')
                ->inline(false)
                ->default(false);
        }

        return $fields;
    }

    /**
     * Extra tylko w kontekście wpłat — należność i opcjonalna pierwsza rata.
     * Kolejne transze dodaje się osobno (historia wpłat).
     *
     * @param  array{
     *   event_code?: ?string,
     *   price_per_person?: float,
     *   price_label?: string,
     *   foreign_hint?: ?string,
     * }|null  $priceContext
     * @return array<int, Forms\Components\Component>
     */
    public static function paymentExtrasFields(?array $priceContext = null): array
    {
        $defaultDue = round((float) ($priceContext['price_per_person'] ?? 0), 2);
        $eventCode = trim((string) ($priceContext['event_code'] ?? ''));
        $priceLabel = trim((string) ($priceContext['price_label'] ?? ''));
        $foreignHint = trim((string) ($priceContext['foreign_hint'] ?? ''));

        $contextLines = [];
        if ($eventCode !== '') {
            $contextLines[] = 'Kod imprezy: '.$eventCode;
        }
        if ($priceLabel !== '') {
            $contextLines[] = 'Cena / os.: '.$priceLabel;
        }
        if ($foreignHint !== '') {
            $contextLines[] = $foreignHint;
        }

        $fields = [];

        if ($contextLines !== []) {
            $fields[] = Forms\Components\Placeholder::make('event_payment_context')
                ->label('Kontekst imprezy')
                ->content(implode("\n", $contextLines))
                ->extraAttributes(['class' => 'whitespace-pre-line text-sm text-gray-600 dark:text-gray-300']);
        }

        $fields[] = Forms\Components\Toggle::make('waive_payment')
            ->label('Bez opłaty')
            ->helperText('Opiekun / gratis — należność 0 PLN, bez windykacji.')
            ->default(false)
            ->live()
            ->afterStateUpdated(function (mixed $state, Set $set) use ($defaultDue): void {
                $set('due_amount_pln', $state ? 0 : $defaultDue);
            });

        $fields[] = Forms\Components\TextInput::make('due_amount_pln')
            ->label('Należna kwota (PLN)')
            ->numeric()
            ->default($defaultDue)
            ->suffix('PLN')
            ->disabled(fn (Get $get): bool => (bool) $get('waive_payment'))
            ->dehydrated()
            ->helperText(
                $defaultDue > 0
                    ? 'Domyślnie z cennika imprezy. Możesz zmienić ręcznie.'
                    : 'Całkowita należność uczestnika. Wpłaty mogą iść w wielu transzach.'
            );

        $fields[] = Forms\Components\Section::make('Pierwsza wpłata (opcjonalnie)')
            ->description('Pozostaw puste, jeśli jeszcze nie było wpłaty. Kolejne raty dodasz w historii.')
            ->collapsed()
            ->visible(fn (Get $get): bool => ! (bool) $get('waive_payment'))
            ->schema([
                Forms\Components\TextInput::make('first_payment_amount_pln')
                    ->label('Kwota pierwszej wpłaty')
                    ->numeric()
                    ->minValue(0)
                    ->suffix('PLN'),

                Forms\Components\DatePicker::make('first_payment_paid_at')
                    ->label('Data wpłaty')
                    ->default(now())
                    ->native(false),

                Forms\Components\Select::make('first_payment_method')
                    ->label('Forma płatności')
                    ->options(\App\Models\EventSettlementParticipantPayment::$paymentMethods)
                    ->default('transfer'),
            ]);

        return $fields;
    }

    /**
     * Kontekst ceny/os. z imprezy do formularza wpłat.
     *
     * @return array{
     *   event_code: ?string,
     *   price_per_person: float,
     *   price_label: string,
     *   foreign_hint: ?string,
     *   foreign_prices: list<array{currency: string, price_per_person: float, label: string}>
     * }
     */
    public static function priceContextForEvent(Event $event): array
    {
        $summary = app(EventPriceSummaryService::class)->forEvent($event, includeNearest: false);
        $foreign = $summary['foreign_prices'] ?? [];
        $foreignHint = null;
        if (is_array($foreign) && $foreign !== []) {
            $parts = array_map(
                fn (array $row): string => (string) ($row['label'] ?? ''),
                $foreign,
            );
            $parts = array_values(array_filter($parts));
            if ($parts !== []) {
                $foreignHint = 'Składowe walut (cena/os.): '.implode(' + ', $parts);
            }
        }

        return [
            'event_code' => $event->code ?: null,
            'price_per_person' => (float) ($summary['price_per_person_rounded'] ?? $summary['price_per_person'] ?? 0),
            'price_label' => (string) ($summary['price_per_person_label'] ?? '—'),
            'foreign_hint' => $foreignHint,
            'foreign_prices' => is_array($foreign) ? $foreign : [],
        ];
    }
}
