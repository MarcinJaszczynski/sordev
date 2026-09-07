<?php

declare(strict_types=1);

namespace App\Filament\Forms;

use App\Actions\Contracts\GenerateEventContractAction;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\Event;
use App\Models\PaymentScheduleTemplate;
use App\Services\AgreementPlaceholderCatalog;
use App\Services\IndividualPaymentSchedulePolicy;
use App\Support\ContractGenerationPriceHints;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

final class ContractGenerationWizardFields
{
    /**
     * @param  (\Closure(): Event)|null  $resolveEvent
     * @param  (\Closure(Get): array<int|string, string>)|null  $attachmentOptions
     * @param  (\Closure(Get): array<int|string>)|null  $attachmentDefaults
     * @return array<int, Forms\Components\Component>
     */
    public static function schema(
        ?\Closure $resolveEvent = null,
        ?\Closure $attachmentOptions = null,
        ?\Closure $attachmentDefaults = null,
    ): array {
        $event = static fn (): ?Event => $resolveEvent ? $resolveEvent() : null;

        return [
            Forms\Components\Wizard::make([
                Forms\Components\Wizard\Step::make('1. Kto zawiera i kto płaci')
                    ->description('Najpierw wybierz model rozliczenia')
                    ->schema([
                        Forms\Components\Radio::make('generation_mode')
                            ->label('Model umowy')
                            ->options([
                                GenerateEventContractAction::MODE_GROUP_ORDERING => 'Szkoła / zamawiający — jedna umowa, jedna płatność',
                                GenerateEventContractAction::MODE_GROUP_PARTICIPANTS => 'Wycieczka grupowa — każdy rodzic płaci za swoje dziecko',
                                GenerateEventContractAction::MODE_INDIVIDUAL => 'Umowy indywidualne — każdy uczestnik osobno',
                            ])
                            ->descriptions([
                                GenerateEventContractAction::MODE_GROUP_ORDERING => 'Jeden link dla dyrektora/opiekuna szkoły. Podpisuje i płaci za całą grupę.',
                                GenerateEventContractAction::MODE_GROUP_PARTICIPANTS => 'Jeden wspólny link. Każdy rodzic wypełnia dane dziecka i płaci swoją kwotę PLN (+ waluta w autokarze).',
                                GenerateEventContractAction::MODE_INDIVIDUAL => 'Jeden wspólny link. Każde otwarcie = osobna umowa + płatność + portal.',
                            ])
                            ->default(GenerateEventContractAction::MODE_GROUP_ORDERING)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get) use ($event): void {
                                static::syncAmountsFromMode($set, $get, $event());
                                static::applySuggestedSchedules($set, $get, $event());
                            }),

                        Forms\Components\Placeholder::make('generation_mode_summary')
                            ->label('Co powstanie po wygenerowaniu')
                            ->content(fn (Get $get): HtmlString => new HtmlString(match ($get('generation_mode')) {
                                GenerateEventContractAction::MODE_GROUP_PARTICIPANTS,
                                GenerateEventContractAction::MODE_INDIVIDUAL => '<ul class="list-disc space-y-1 pl-5 text-sm">'
                                    .'<li>Jeden <strong>wspólny link</strong> do rozesłania.</li>'
                                    .'<li>Klient uzupełnia dane → umowa, harmonogram, płatność, portal.</li>'
                                    .'<li>Kwota na umowie = <strong>cena/os. × osoby na umowę</strong> (nie suma całej grupy).</li>'
                                    .'</ul>',
                                default => '<ul class="list-disc space-y-1 pl-5 text-sm">'
                                    .'<li>Jedna <strong>umowa grupowa</strong> + link dla zamawiającego.</li>'
                                    .'<li>Kwota = suma za grupę (PLN).</li>'
                                    .'</ul>',
                            })),

                        Forms\Components\Toggle::make('participants_fill_separately')
                            ->label('Dodatkowo: osobny link tylko do danych uczestników')
                            ->helperText('Tylko przy płatności szkoły. Drugi link bez ponownej płatności.')
                            ->default(false)
                            ->visible(fn (Get $get): bool => $get('generation_mode') === GenerateEventContractAction::MODE_GROUP_ORDERING),
                    ]),

                Forms\Components\Wizard\Step::make('2. Szablon i kwoty')
                    ->description('Treść umowy i ceny (PLN + waluta)')
                    ->schema([
                        Forms\Components\Placeholder::make('price_hint')
                            ->label('Podpowiedź ze szablonu imprezy')
                            ->content(function () use ($event): HtmlString {
                                $e = $event();
                                if (! $e) {
                                    return new HtmlString('<span class="text-sm">Brak imprezy.</span>');
                                }

                                return new HtmlString(ContractGenerationPriceHints::forEvent($e)['hint_html']);
                            }),

                        Forms\Components\Select::make('contract_template_id')
                            ->label('Szablon treści umowy')
                            ->helperText('Treść umowy = content szablonu ze znacznikami [TAG]. Unikaj testowych stubów typu „ddd”.')
                            ->options(function (Get $get): array {
                                $mode = $get('generation_mode');
                                $type = $mode === GenerateEventContractAction::MODE_GROUP_ORDERING
                                    ? Contract::TYPE_GROUP
                                    : Contract::TYPE_INDIVIDUAL;

                                return ContractTemplate::optionsForSelect($type);
                            })
                            ->searchable()
                            ->nullable()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('custom_placeholder_values', [])),

                        Forms\Components\Placeholder::make('template_preview')
                            ->label('Podgląd szablonu / mapowanie pól')
                            ->content(fn (Get $get): HtmlString => new HtmlString(self::templatePreviewHtml($get))),

                        ...ContractTemplateCustomValueFields::schema(),

                        Forms\Components\TextInput::make('title')
                            ->label('Tytuł w panelu')
                            ->required()
                            ->default(fn (Get $get): string => match ($get('generation_mode')) {
                                GenerateEventContractAction::MODE_GROUP_ORDERING => 'Umowa imprezy',
                                default => 'Umowa uczestnika',
                            }),

                        Forms\Components\TextInput::make('participant_count')
                            ->label('Liczba osób w grupie')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->default(fn () => max(1, (int) ($event()?->participant_count ?? 1)))
                            ->visible(fn (Get $get): bool => $get('generation_mode') === GenerateEventContractAction::MODE_GROUP_ORDERING)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, Get $get) use ($event): void {
                                static::syncAmountsFromMode($set, $get, $event());
                                static::applySuggestedSchedules($set, $get, $event());
                            }),

                        Forms\Components\TextInput::make('paying_participants_count')
                            ->label('Ilu osobom planujesz wysłać link')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->default(fn () => max(1, (int) ($event()?->participant_count ?? 1)))
                            ->visible(fn (Get $get): bool => $get('generation_mode') !== GenerateEventContractAction::MODE_GROUP_ORDERING)
                            ->helperText('Metryka. Link jest jeden — każdy otwiera go osobno.'),

                        Forms\Components\TextInput::make('participants_on_contract')
                            ->label('Ile osób na jedną umowę (np. rodzeństwo)')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required()
                            ->visible(fn (Get $get): bool => $get('generation_mode') !== GenerateEventContractAction::MODE_GROUP_ORDERING)
                            ->helperText('1 = jedno dziecko. 2 = PLN i waluta × 2 na tej umowie.')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, Get $get) use ($event): void {
                                static::syncAmountsFromMode($set, $get, $event());
                                static::applySuggestedSchedules($set, $get, $event());
                            }),

                        Forms\Components\TextInput::make('unit_price')
                            ->label('Cena za 1 osobę (PLN)')
                            ->numeric()
                            ->suffix('PLN')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, Get $get) use ($event): void {
                                if ((bool) $get('override_amount_due')) {
                                    return;
                                }
                                static::syncAmountsFromMode($set, $get, $event(), keepUnit: true);
                                static::applySuggestedSchedules($set, $get, $event());
                            }),

                        Forms\Components\Toggle::make('override_amount_due')
                            ->label('Ustaw inną kwotę na umowę (nie przeliczaj z ceny × osoby)')
                            ->default(false)
                            ->live()
                            ->visible(fn (Get $get): bool => $get('generation_mode') !== GenerateEventContractAction::MODE_GROUP_ORDERING)
                            ->afterStateUpdated(function (Set $set, Get $get, mixed $state) use ($event): void {
                                if (! $state) {
                                    static::syncAmountsFromMode($set, $get, $event());
                                }
                            }),

                        Forms\Components\TextInput::make('amount_due')
                            ->label(fn (Get $get): string => $get('generation_mode') === GenerateEventContractAction::MODE_GROUP_ORDERING
                                ? 'Suma PLN do zapłaty przez zamawiającego'
                                : 'PLN na tę umowę (= cena/os. × osoby na umowę)')
                            ->numeric()
                            ->required()
                            ->suffix('PLN')
                            ->disabled(fn (Get $get): bool => $get('generation_mode') !== GenerateEventContractAction::MODE_GROUP_ORDERING
                                && ! (bool) $get('override_amount_due'))
                            ->dehydrated()
                            ->helperText(fn (Get $get): string => $get('generation_mode') === GenerateEventContractAction::MODE_GROUP_ORDERING
                                ? 'Suma za całą grupę. Waluty obce w osobnych ratach.'
                                : 'Kanonicznie: unit_price × osoby na umowę. Nie mylić z sumą całej grupy.'),

                        Forms\Components\Toggle::make('requires_diet')
                            ->label('Dopłata za dietę specjalną')
                            ->helperText('Cennik trafi na umowę. Kwota doliczy się dopiero, gdy klient wybierze dietę w portalu.')
                            ->default(false)
                            ->live()
                            ->visible(fn (): bool => Schema::hasColumn('contracts', 'requires_diet'))
                            ->afterStateUpdated(function (Set $set, mixed $state): void {
                                if ($state && Schema::hasColumn('contracts', 'diet_daily_pln')) {
                                    $set('diet_daily_pln', 20);
                                }
                            }),

                        Forms\Components\TextInput::make('diet_daily_pln')
                            ->label('Dopłata diety (PLN / dzień / osoba)')
                            ->numeric()
                            ->minValue(0)
                            ->default(20)
                            ->suffix('PLN')
                            ->required(fn (Get $get): bool => (bool) $get('requires_diet'))
                            ->visible(fn (Get $get): bool => Schema::hasColumn('contracts', 'diet_daily_pln') && (bool) $get('requires_diet')),

                        Forms\Components\TagsInput::make('diet_options')
                            ->label('Opcje diet w portalu')
                            ->placeholder('Wpisz opcję diety')
                            ->helperText('Klient wybierze jedną z opcji. Puste = wolny tekst diety.')
                            ->visible(fn (Get $get): bool => (bool) $get('requires_diet')),

                        Forms\Components\Repeater::make('contract_extras')
                            ->label('Inne dodatkowe świadczenia')
                            ->helperText('Np. pokój 1-os., ubezpieczenie. Klient wybierze w portalu — wtedy doliczymy dopłatę.')
                            ->schema([
                                Forms\Components\TextInput::make('label')
                                    ->label('Nazwa')
                                    ->required()
                                    ->maxLength(120),
                                Forms\Components\TextInput::make('key')
                                    ->label('Klucz (opcjonalnie)')
                                    ->maxLength(64)
                                    ->helperText('Puste = z nazwy.'),
                                Forms\Components\Select::make('pricing')
                                    ->label('Sposób liczenia')
                                    ->options([
                                        'flat' => 'Kwota stała',
                                        'per_person' => 'PLN / osoba',
                                        'per_day' => 'PLN / dzień / osoba',
                                    ])
                                    ->default('flat')
                                    ->required(),
                                Forms\Components\TextInput::make('amount_pln')
                                    ->label('Kwota PLN')
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->required()
                                    ->suffix('PLN'),
                                Forms\Components\TagsInput::make('options')
                                    ->label('Opcje wyboru')
                                    ->placeholder('Wpisz opcję wyboru'),
                                Forms\Components\Select::make('applies')
                                    ->label('Dotyczy')
                                    ->options([
                                        'participant' => 'Per uczestnik (wybór w portalu)',
                                        'contract' => 'Cała umowa (zawsze)',
                                    ])
                                    ->default('participant'),
                            ])
                            ->columns(2)
                            ->default([])
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null),

                        Forms\Components\Placeholder::make('client_outcome')
                            ->label('Co trafi do klienta')
                            ->content(fn (Get $get): HtmlString => new HtmlString(self::clientOutcomeHtml($get, $event()))),

                        Forms\Components\Placeholder::make('foreign_amount_hint')
                            ->label('Waluta na osobę (ze szablonu)')
                            ->content(function () use ($event): string {
                                $e = $event();
                                if (! $e) {
                                    return '—';
                                }
                                $hints = ContractGenerationPriceHints::forEvent($e);
                                if ($hints['foreign'] === []) {
                                    return 'Brak składowej walutowej w szablonie.';
                                }

                                return collect($hints['foreign'])
                                    ->map(fn (array $row): string => (string) ($row['label'] ?? ''))
                                    ->filter()
                                    ->implode(' · ');
                            })
                            ->visible(fn (Get $get): bool => $get('generation_mode') !== GenerateEventContractAction::MODE_GROUP_ORDERING),

                        Forms\Components\Toggle::make('include_foreign_currency')
                            ->label('Uwzględnij składową walutową na umowie')
                            ->helperText('Domyślnie wyłączone. Włącz tylko gdy chcesz dopuścić wpłatę EUR/innej waluty (osobna rata).')
                            ->default(false)
                            ->live()
                            ->visible(function () use ($event): bool {
                                $e = $event();
                                if (! $e) {
                                    return false;
                                }

                                return ContractGenerationPriceHints::forEvent($e)['foreign'] !== [];
                            })
                            ->afterStateUpdated(function (Set $set, Get $get, mixed $state) use ($event): void {
                                static::resyncForeignInSchedules($set, $get, $event());
                            }),

                        Forms\Components\Select::make('foreign_paid_by')
                            ->label('Gdzie płatna waluta')
                            ->options([
                                'pilot' => 'U pilota / w autokarze (gotówka)',
                                'office' => 'W biurze (przelew / kasa)',
                            ])
                            ->default('pilot')
                            ->required(fn (Get $get): bool => (bool) $get('include_foreign_currency'))
                            ->live()
                            ->visible(fn (Get $get): bool => (bool) $get('include_foreign_currency'))
                            ->afterStateUpdated(function (Set $set, Get $get) use ($event): void {
                                static::resyncForeignInSchedules($set, $get, $event());
                            }),
                    ]),

                Forms\Components\Wizard\Step::make('3. Harmonogram wpłat')
                    ->description('PLN w terminie; waluta często w autokarze')
                    ->schema([
                        Forms\Components\Placeholder::make('payment_policy_hint')
                            ->label('Reguła płatności')
                            ->content(function () use ($event): HtmlString {
                                $e = $event();
                                if (! $e) {
                                    return new HtmlString('');
                                }

                                return new HtmlString(app(IndividualPaymentSchedulePolicy::class)->policyHintHtml($e));
                            })
                            ->visible(fn (Get $get): bool => $get('generation_mode') !== GenerateEventContractAction::MODE_GROUP_ORDERING),

                        Forms\Components\Placeholder::make('schedule_from_percent_hint')
                            ->label('Podpowiedź z % i dni do startu imprezy')
                            ->content(function (Get $get) use ($event): HtmlString {
                                $e = $event();
                                if (! $e) {
                                    return new HtmlString('<span class="text-sm">Brak imprezy.</span>');
                                }

                                $hints = ContractGenerationPriceHints::forEvent($e);
                                $multiplier = $get('generation_mode') === GenerateEventContractAction::MODE_GROUP_ORDERING
                                    ? max(1, (int) ($get('participant_count') ?? 1))
                                    : max(1, (int) ($get('participants_on_contract') ?? 1));

                                return new HtmlString(ContractGenerationPriceHints::schedulePreviewHtml(
                                    $e,
                                    max(0.01, (float) ($hints['pln_per_person'] ?? 0)),
                                    $multiplier,
                                ));
                            }),

                        Forms\Components\Select::make('payment_schedule_template_id')
                            ->label('Szablon harmonogramu (biblioteka)')
                            ->helperText('Globalny szablon rat — przelicza %/kwoty na terminy względem startu imprezy. Ma pierwszeństwo przed szablonem imprezy.')
                            ->options(function (Get $get): array {
                                $mode = $get('generation_mode');
                                $type = $mode === GenerateEventContractAction::MODE_GROUP_ORDERING
                                    ? Contract::TYPE_GROUP
                                    : Contract::TYPE_INDIVIDUAL;

                                return Schema::hasTable('payment_schedule_templates')
                                    ? PaymentScheduleTemplate::optionsForSelect($type)
                                    : [];
                            })
                            ->searchable()
                            ->nullable()
                            ->live()
                            ->visible(fn (): bool => Schema::hasTable('payment_schedule_templates'))
                            ->afterStateUpdated(function (Set $set, Get $get, mixed $state) use ($event): void {
                                if (! filled($state)) {
                                    return;
                                }
                                $set('use_event_payment_template', false);
                                static::applySuggestedSchedules($set, $get, $event(), keepManualToggle: true);
                            }),

                        Forms\Components\Toggle::make('use_event_payment_template')
                            ->label('Weź gotowy harmonogram z tej imprezy')
                            ->helperText('Przelicza % ceny/os. na kwoty i terminy z D±N względem startu.')
                            ->default(function () use ($event): bool {
                                if (! Schema::hasTable('event_payment_installment_templates')) {
                                    return false;
                                }
                                $e = $event();

                                return $e !== null && $e->paymentInstallmentTemplates()->exists();
                            })
                            ->visible(fn (Get $get): bool => Schema::hasTable('event_payment_installment_templates')
                                && blank($get('payment_schedule_template_id')))
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, mixed $state) use ($event): void {
                                if (! $state) {
                                    return;
                                }
                                static::applySuggestedSchedules($set, $get, $event());
                            }),

                        Forms\Components\Select::make('payment_scheme')
                            ->label('Schemat')
                            ->options([
                                Contract::PAYMENT_SCHEME_LUMP_SUM => 'Jedna wpłata PLN (+ osobno waluta w ratach jeśli dodasz)',
                                Contract::PAYMENT_SCHEME_INSTALLMENTS => 'Raty (PLN i/lub waluta)',
                            ])
                            ->default(Contract::PAYMENT_SCHEME_LUMP_SUM)
                            ->live()
                            ->visible(fn (Get $get): bool => ! (bool) $get('use_event_payment_template')
                                && blank($get('payment_schedule_template_id')))
                            ->disableOptionWhen(function (string $value, Get $get) use ($event): bool {
                                if ($value !== Contract::PAYMENT_SCHEME_LUMP_SUM) {
                                    return false;
                                }
                                if ($get('generation_mode') === GenerateEventContractAction::MODE_GROUP_ORDERING) {
                                    return false;
                                }
                                $e = $event();

                                return $e !== null && app(IndividualPaymentSchedulePolicy::class)->requiresInstallments($e);
                            }),

                        Forms\Components\Repeater::make('payment_schedules')
                            ->label('Harmonogram transz')
                            ->helperText(fn (Get $get): ?string => ContractGroupPricingFields::installmentsHelperText(
                                $get('payment_schedules'),
                                $get('amount_due'),
                            ))
                            ->schema([
                                Forms\Components\TextInput::make('label')
                                    ->label('Opis transzy')
                                    ->maxLength(255)
                                    ->placeholder('Wpisz opis transzy'),
                                Forms\Components\TextInput::make('amount')
                                    ->label('Kwota PLN')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->suffix('PLN')
                                    ->helperText('0 = rata tylko w walucie (np. dla pilota)'),
                                Forms\Components\TextInput::make('amount_foreign')
                                    ->label('Kwota waluty')
                                    ->numeric()
                                    ->minValue(0)
                                    ->placeholder('Wpisz kwotę waluty (opcjonalnie)'),
                                Forms\Components\TextInput::make('currency_code')
                                    ->label('Waluta')
                                    ->maxLength(8)
                                    ->placeholder('Wpisz kod waluty'),
                                Forms\Components\Select::make('paid_by')
                                    ->label('Odbiorca')
                                    ->options([
                                        'office' => 'Biuro',
                                        'pilot' => 'Pilot',
                                    ])
                                    ->placeholder('Wybierz odbiorcę'),
                                Forms\Components\DatePicker::make('due_from')
                                    ->label('Termin płatności od')
                                    ->native(false),
                                Forms\Components\DatePicker::make('due_to')
                                    ->label('Termin płatności do')
                                    ->native(false)
                                    ->helperText('Deadline raty (UFG / przypomnienia). Synchronizowane z due_date.'),
                                Forms\Components\DatePicker::make('due_date')
                                    ->label('Termin (legacy)')
                                    ->native(false)
                                    ->visible(false)
                                    ->dehydrated(true),
                                Forms\Components\Textarea::make('notes')
                                    ->label('Uwagi')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ])
                            ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                            ->default([])
                            ->addActionLabel('Dodaj transzę')
                            ->reorderable()
                            ->collapsible()
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => (bool) $get('use_event_payment_template')
                                || $get('payment_scheme') === Contract::PAYMENT_SCHEME_INSTALLMENTS
                                || filled($get('payment_schedules'))),

                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('prefill_schedules')
                                ->label('Wstaw raty z % / D±N (lub zgodne z regułą 30 dni)')
                                ->action(function (Set $set, Get $get) use ($event): void {
                                    static::applySuggestedSchedules($set, $get, $event(), keepManualToggle: true);
                                }),
                        ]),
                    ]),

                Forms\Components\Wizard\Step::make('4. Załączniki (opcjonalnie)')
                    ->description('Pliki dla klienta + dane UFG')
                    ->schema([
                        Forms\Components\CheckboxList::make('selected_attachments')
                            ->label('Załączniki do umowy')
                            ->options(fn (Get $get): array => $attachmentOptions ? $attachmentOptions($get) : [])
                            ->columns(1)
                            ->default(fn (Get $get): array => $attachmentDefaults ? $attachmentDefaults($get) : [])
                            ->helperText('Klient zobaczy te pliki razem z umową.'),

                        Forms\Components\Section::make('Dane UFG / TFG (z konfiguracji imprezy)')
                            ->description('Domyślne z imprezy — możesz poprawić przed generowaniem.')
                            ->collapsed()
                            ->schema(ContractTfgForm::schema(false, $resolveEvent)),
                    ]),
            ])
                ->skippable(false)
                ->columnSpanFull(),
        ];
    }

    public static function syncAmountsFromMode(Set $set, Get $get, ?Event $event, bool $keepUnit = false): void
    {
        if (! $event) {
            return;
        }

        $hints = ContractGenerationPriceHints::forEvent($event);
        $pln = $keepUnit
            ? round((float) ($get('unit_price') ?? $hints['pln_per_person']), 2)
            : $hints['pln_per_person'];
        $mode = $get('generation_mode');

        if ($mode === GenerateEventContractAction::MODE_GROUP_ORDERING) {
            $count = max(1, (int) ($get('participant_count') ?? $event->participant_count ?? 1));
            $set('unit_price', $pln);
            $set('amount_due', round($pln * $count, 2));
            $set('title', 'Umowa imprezy');
            $set('override_amount_due', false);

            return;
        }

        $slots = max(1, (int) ($get('participants_on_contract') ?? 1));
        $set('unit_price', $pln);
        if (! (bool) $get('override_amount_due')) {
            $set('amount_due', round($pln * $slots, 2));
        }
        $set('title', 'Umowa uczestnika');
        $set('paying_participants_count', max(1, (int) ($get('paying_participants_count') ?? $event->participant_count ?? 1)));
    }

    public static function applySuggestedSchedules(Set $set, Get $get, ?Event $event, bool $keepManualToggle = false): void
    {
        if (! $event) {
            return;
        }

        $hints = ContractGenerationPriceHints::forEvent($event);
        $mode = $get('generation_mode');
        $multiplier = $mode === GenerateEventContractAction::MODE_GROUP_ORDERING
            ? max(1, (int) ($get('participant_count') ?? 1))
            : max(1, (int) ($get('participants_on_contract') ?? 1));
        $includeForeign = (bool) ($get('include_foreign_currency') ?? false);
        $foreignPaidBy = (string) ($get('foreign_paid_by') ?: 'pilot');
        $amountDue = round((float) ($get('amount_due') ?? ($hints['pln_per_person'] * $multiplier)), 2);

        $templateId = (int) ($get('payment_schedule_template_id') ?? 0);
        if ($templateId > 0 && Schema::hasTable('payment_schedule_templates')) {
            $template = PaymentScheduleTemplate::query()->with('installments')->find($templateId);
            if ($template && $template->installments->isNotEmpty()) {
                $schedules = app(\App\Services\PaymentScheduleTemplateService::class)
                    ->materializeForEvent($template, $event, max(0.01, $amountDue));
                $set('use_event_payment_template', false);
                $set('payment_schedules', $schedules);
                $set('payment_scheme', Contract::PAYMENT_SCHEME_INSTALLMENTS);

                return;
            }
        }

        $schedules = ContractGenerationPriceHints::scaleSchedules(
            ContractGenerationPriceHints::defaultSchedulesPerPerson(
                $event,
                max(0.01, (float) ($get('unit_price') ?? $hints['pln_per_person'])),
                $hints['foreign'],
                $includeForeign,
                $foreignPaidBy,
            ),
            $multiplier,
        );

        if ($mode !== GenerateEventContractAction::MODE_GROUP_ORDERING) {
            $enforced = app(IndividualPaymentSchedulePolicy::class)->enforceForIndividual(
                $event,
                $amountDue,
                Contract::PAYMENT_SCHEME_INSTALLMENTS,
                $schedules,
                includeForeign: $includeForeign,
            );
            $schedules = $enforced['payment_schedules'];
        }

        if (! $keepManualToggle) {
            $set('use_event_payment_template', $hints['has_event_installment_template'] || $schedules !== []);
        } else {
            $set('use_event_payment_template', false);
        }

        $set('payment_schedules', $schedules);
        $set('payment_scheme', $schedules !== []
            ? Contract::PAYMENT_SCHEME_INSTALLMENTS
            : Contract::PAYMENT_SCHEME_LUMP_SUM);
    }

    public static function resyncForeignInSchedules(Set $set, Get $get, ?Event $event): void
    {
        if (! $event) {
            return;
        }

        $hints = ContractGenerationPriceHints::forEvent($event);
        $mode = $get('generation_mode');
        $multiplier = $mode === GenerateEventContractAction::MODE_GROUP_ORDERING
            ? max(1, (int) ($get('participant_count') ?? 1))
            : max(1, (int) ($get('participants_on_contract') ?? 1));
        $includeForeign = (bool) ($get('include_foreign_currency') ?? false);
        $foreignPaidBy = (string) ($get('foreign_paid_by') ?: 'pilot');
        $current = is_array($get('payment_schedules')) ? $get('payment_schedules') : [];

        $set('payment_schedules', ContractGenerationPriceHints::applyForeignCurrencyToSchedules(
            $current,
            $includeForeign,
            $foreignPaidBy,
            $hints['foreign'],
            $multiplier,
            $event,
        ));
    }

    /**
     * @param  (\Closure(): Event)|null  $resolveEvent
     * @return array<string, mixed>
     */
    public static function fillDefaults(?\Closure $resolveEvent = null): array
    {
        $event = $resolveEvent ? $resolveEvent() : null;
        $count = max(1, (int) ($event?->participant_count ?? 1));
        $hints = $event ? ContractGenerationPriceHints::forEvent($event) : null;
        $unit = $hints['pln_per_person'] ?? 0.0;
        $hasTemplate = (bool) ($hints['has_event_installment_template'] ?? false);
        $requiresInstallments = $event
            ? app(IndividualPaymentSchedulePolicy::class)->requiresInstallments($event)
            : false;

        $schedules = [];
        if ($hasTemplate && $hints) {
            $schedules = ContractGenerationPriceHints::scaleSchedules($hints['default_schedules_per_person'], $count);
        } elseif ($requiresInstallments && $event) {
            $schedules = app(IndividualPaymentSchedulePolicy::class)
                ->suggestedCompliantSchedules($event, round($unit * $count, 2));
        }

        $defaults = [
            'generation_mode' => GenerateEventContractAction::MODE_GROUP_ORDERING,
            'participants_fill_separately' => false,
            'participants_on_contract' => 1,
            'override_amount_due' => false,
            'include_foreign_currency' => false,
            'foreign_paid_by' => 'pilot',
            'use_event_payment_template' => $hasTemplate || $requiresInstallments,
            'title' => 'Umowa imprezy',
            'participant_count' => $count,
            'paying_participants_count' => $count,
            'unit_price' => $unit,
            'amount_due' => round($unit * $count, 2),
            'payment_scheme' => ($hasTemplate || $requiresInstallments)
                ? Contract::PAYMENT_SCHEME_INSTALLMENTS
                : Contract::PAYMENT_SCHEME_LUMP_SUM,
            'payment_schedules' => $schedules,
        ];

        if ($event) {
            $defaults = array_merge(
                $defaults,
                app(\App\Services\ContractTfgSetupService::class)->defaultsFromEvent($event),
            );
        }

        return $defaults;
    }

    private static function templatePreviewHtml(Get $get): string
    {
        $templateId = $get('contract_template_id');
        if (! filled($templateId)) {
            return '<p class="text-sm text-gray-500">Wybierz szablon, żeby zobaczyć podgląd treści i znaczniki.</p>';
        }

        $template = ContractTemplate::query()->find((int) $templateId);
        if (! $template) {
            return '<p class="text-sm text-danger-600">Nie znaleziono szablonu.</p>';
        }

        $raw = (string) ($template->content ?? '');
        $plain = trim(Str::of(strip_tags($raw))->replaceMatches('/\s+/', ' ')->toString());
        $isStub = mb_strlen($plain) < 20 || ! str_contains($raw, '[');

        $preview = e(Str::limit($plain !== '' ? $plain : '(pusta treść)', 280));
        $warn = $isStub
            ? '<p class="mt-2 text-sm font-medium text-amber-700 dark:text-amber-300">Uwaga: treść wygląda na stub/test (np. „ddd”). Uzupełnij szablon znacznikami [TAG] przed generowaniem.</p>'
            : '';

        $clientFilled = 'Klient uzupełni w formularzu: imię/nazwisko zamawiającego, email, telefon, adres, dane uczestnika.';
        $fromEvent = 'Z imprezy/generatora: nazwa, terminy, kwota [KWOTA], cena/os. [CENA_JEDNOSTKOWA], harmonogram [HARMONOGRAM_PLATNOSCI].';

        $tags = collect(app(AgreementPlaceholderCatalog::class)->definitions())
            ->filter(fn (array $def): bool => str_contains($raw, $def['tag']))
            ->take(12)
            ->map(fn (array $def): string => '<code>'.e($def['tag']).'</code> '.e($def['label']))
            ->implode(', ');

        return '<div class="space-y-2 text-sm">'
            .'<p><strong>Podgląd:</strong> '.$preview.'</p>'
            .$warn
            .'<p>'.$clientFilled.'</p>'
            .'<p>'.$fromEvent.'</p>'
            .($tags !== '' ? '<p><strong>Znaczniki w szablonie:</strong> '.$tags.'</p>' : '<p class="text-amber-700">Brak znaczników [TAG] w treści.</p>')
            .'</div>';
    }

    private static function clientOutcomeHtml(Get $get, ?Event $event): string
    {
        $mode = $get('generation_mode');
        $unit = round((float) ($get('unit_price') ?? 0), 2);
        $amount = round((float) ($get('amount_due') ?? 0), 2);
        $slots = max(1, (int) ($get('participants_on_contract') ?? 1));
        $group = max(1, (int) ($get('participant_count') ?? 1));

        if ($mode === GenerateEventContractAction::MODE_GROUP_ORDERING) {
            return '<ul class="list-disc pl-5 text-sm">'
                .'<li>Kwota na umowie: <strong>'.e(number_format($amount, 2, ',', ' ')).' PLN</strong> ('.$group.' os. × '.e(number_format($unit, 2, ',', ' ')).').</li>'
                .'<li>Klient (zamawiający) podpisuje i płaci jedną płatnością / ratami.</li>'
                .'</ul>';
        }

        $fxNote = '';
        if ((bool) $get('include_foreign_currency')) {
            $hints = $event ? ContractGenerationPriceHints::forEvent($event) : null;
            $fxParts = [];
            foreach (($hints['foreign'] ?? []) as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $code = strtoupper((string) ($row['currency'] ?? ''));
                $fx = round((float) ($row['price_per_person'] ?? 0) * $slots, 2);
                if ($code !== '' && $fx > 0) {
                    $fxParts[] = number_format($fx, 2, ',', ' ').' '.$code;
                }
            }
            $place = ($get('foreign_paid_by') ?? 'pilot') === 'office' ? 'biuro' : 'pilot/autokar';
            if ($fxParts !== []) {
                $fxNote = ' + <strong>'.e(implode(' + ', $fxParts)).'</strong> ('.$place.')';
            }
        }

        $policy = $event && app(IndividualPaymentSchedulePolicy::class)->requiresInstallments($event)
            ? '<li>Wymagane raty (reguła 30 dni przed startem).</li>'
            : '';

        return '<ul class="list-disc pl-5 text-sm">'
            .'<li>Każdy link → umowa na <strong>'.e(number_format($amount, 2, ',', ' ')).' PLN</strong>'
            .$fxNote
            .' (= '.e(number_format($unit, 2, ',', ' ')).' PLN × '.$slots.' os.).</li>'
            .'<li>Zamawiający/płatnik = osoba z formularza (nie dane szkoły z imprezy).</li>'
            .'<li>Nie jest to suma całej grupy ('.max(1, (int) ($event?->participant_count ?? 1)).' os.).</li>'
            .$policy
            .'</ul>';
    }
}
