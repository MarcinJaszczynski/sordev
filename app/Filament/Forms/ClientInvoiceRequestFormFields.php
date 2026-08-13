<?php

declare(strict_types=1);

namespace App\Filament\Forms;

use App\Models\ClientInvoiceRequest;
use App\Models\Event;
use Filament\Forms;
use Filament\Forms\Get;
use Illuminate\Support\HtmlString;

final class ClientInvoiceRequestFormFields
{
    /**
     * Formularz ręcznego wniosku o fakturę (Inbox / Finanse imprezy / User).
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function adminCreateSchema(?int $lockedEventId = null): array
    {
        $event = $lockedEventId ? Event::query()->find($lockedEventId) : null;

        return self::adminModalSchema($lockedEventId, $event);
    }

    /**
     * Formularz modala biura — sekcje, układ jak w portalu klienta.
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function adminModalSchema(?int $lockedEventId = null, ?Event $event = null): array
    {
        $event ??= $lockedEventId ? Event::query()->find($lockedEventId) : null;

        $schema = [];

        if ($lockedEventId !== null && $event) {
            $schema[] = Forms\Components\Section::make('Impreza')
                ->icon('heroicon-o-calendar-days')
                ->description('Wniosek zostanie powiązany z tą imprezą i pojawi się w skrzynce wniosków.')
                ->schema([
                    Forms\Components\Hidden::make('event_id')
                        ->default($lockedEventId)
                        ->dehydrated(),
                    Forms\Components\Placeholder::make('event_summary')
                        ->hiddenLabel()
                        ->content(self::eventSummaryHtml($event)),
                ]);
        } else {
            $schema[] = Forms\Components\Section::make('Impreza')
                ->icon('heroicon-o-calendar-days')
                ->description('Wybierz imprezę, dla której wystawiasz wniosek.')
                ->schema([
                    Forms\Components\Select::make('event_id')
                        ->label('Impreza')
                        ->required()
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => Event::query()
                            ->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                            ->orderByDesc('id')
                            ->limit(30)
                            ->get()
                            ->mapWithKeys(fn (Event $event): array => [$event->id => ($event->code ?: '#'.$event->id).' — '.$event->name])
                            ->all())
                        ->getOptionLabelUsing(fn ($value): ?string => optional(Event::find($value), fn (Event $event): string => ($event->code ?: '#'.$event->id).' — '.$event->name)),
                ]);
        }

        $schema[] = Forms\Components\Section::make('Nabywca faktury')
            ->icon('heroicon-o-user-circle')
            ->schema([
                Forms\Components\Radio::make('buyer_type')
                    ->label('Typ nabywcy')
                    ->options(ClientInvoiceRequest::$buyerTypes)
                    ->default(ClientInvoiceRequest::BUYER_COMPANY)
                    ->inline()
                    ->required()
                    ->live()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('company_name')
                    ->label(fn (Get $get): string => $get('buyer_type') === ClientInvoiceRequest::BUYER_PERSON
                        ? 'Imię i nazwisko'
                        : 'Nazwa firmy / instytucji')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('nip')
                    ->label('NIP')
                    ->maxLength(16)
                    ->required(fn (Get $get): bool => $get('buyer_type') === ClientInvoiceRequest::BUYER_COMPANY)
                    ->visible(fn (Get $get): bool => $get('buyer_type') === ClientInvoiceRequest::BUYER_COMPANY),
                Forms\Components\TextInput::make('invoice_email')
                    ->label('E-mail do faktury')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('applicant_phone')
                    ->label('Telefon')
                    ->tel()
                    ->maxLength(50),
            ])
            ->columns(2);

        $schema[] = Forms\Components\Section::make('Adres nabywcy')
            ->icon('heroicon-o-map-pin')
            ->description('Opcjonalnie — jeśli klient podał adres do faktury.')
            ->schema([
                Forms\Components\TextInput::make('street')
                    ->label('Ulica')
                    ->maxLength(255),
                Forms\Components\TextInput::make('house_number')
                    ->label('Nr domu / lokalu')
                    ->maxLength(32),
                Forms\Components\TextInput::make('postal_code')
                    ->label('Kod pocztowy')
                    ->maxLength(16),
                Forms\Components\TextInput::make('city')
                    ->label('Miasto')
                    ->maxLength(120),
            ])
            ->columns(2)
            ->collapsed();

        $schema[] = Forms\Components\Section::make('Kwota i referencja')
            ->icon('heroicon-o-banknotes')
            ->schema([
                Forms\Components\TextInput::make('amount')
                    ->label('Kwota do zafakturowania')
                    ->numeric()
                    ->minValue(0)
                    ->suffix('PLN'),
                Forms\Components\TextInput::make('payment_reference')
                    ->label('Referencja płatności / umowy')
                    ->maxLength(120)
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('notes')
                    ->label('Uwagi dla księgowości')
                    ->rows(3)
                    ->maxLength(2000)
                    ->placeholder('Np. numer przelewu, transza, uwagi klienta…')
                    ->columnSpanFull(),
            ])
            ->columns(2);

        return $schema;
    }

    private static function eventSummaryHtml(Event $event): HtmlString
    {
        $code = e($event->code ?: '#'.$event->id);
        $name = e($event->name);
        $dates = $event->start_date
            ? e($event->start_date->format('d.m.Y'))
                .($event->end_date && $event->end_date->ne($event->start_date)
                    ? ' — '.e($event->end_date->format('d.m.Y'))
                    : '')
            : null;

        $meta = array_filter([
            $dates,
            filled($event->client_name) ? 'Klient: '.e($event->client_name) : null,
        ]);

        $metaHtml = $meta !== []
            ? '<div class="mt-1 text-sm text-gray-600 dark:text-gray-300">'.implode(' · ', $meta).'</div>'
            : '';

        return new HtmlString(
            '<div class="rounded-xl border border-primary-200 bg-primary-50/70 px-4 py-3 dark:border-primary-800/60 dark:bg-primary-950/40">'
            .'<div class="flex items-start gap-3">'
            .'<span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-100 text-primary-700 dark:bg-primary-900/50 dark:text-primary-200">'
            .'<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3.75 9.75h16.5M4.5 6.75h15a1.5 1.5 0 0 1 1.5 1.5v12a1.5 1.5 0 0 1-1.5 1.5h-15a1.5 1.5 0 0 1-1.5-1.5v-12a1.5 1.5 0 0 1 1.5-1.5Z"/></svg>'
            .'</span>'
            .'<div class="min-w-0">'
            .'<div class="font-semibold text-gray-900 dark:text-white">'.$code.' · '.$name.'</div>'
            .$metaHtml
            .'</div></div></div>'
        );
    }
}
