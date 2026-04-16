<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Models\ContractTemplate;
use App\Models\EventAgreement;
use App\Models\EventSettlementParticipantPayment;
use App\Services\AgreementPaymentSyncService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AgreementsRelationManager extends RelationManager
{
    protected static string $relationship = 'agreements';
    protected static ?string $title = 'Umowy i płatności';
    protected static ?string $recordTitleAttribute = 'agreement_number';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Podstawowe dane umowy')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('contract_template_id')
                        ->label('Szablon umowy')
                        ->options(fn () => ContractTemplate::orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->nullable(),

                    Forms\Components\Select::make('agreement_type')
                        ->label('Typ umowy')
                        ->options(EventAgreement::$types)
                        ->default(EventAgreement::TYPE_GROUP)
                        ->live()
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            $event = $this->getOwnerRecord();
                            $participantCount = max(1, (int) ($event->participant_count ?? 1));
                            $pricePerPerson = $event->resolvedPricePerPerson($participantCount);

                            if ($state === EventAgreement::TYPE_INDIVIDUAL) {
                                $set('amount_due', $pricePerPerson);
                                $set('participant_count', 1);
                            } else {
                                $set('amount_due', $pricePerPerson * $participantCount);
                                $set('participant_count', $participantCount);
                            }
                        })
                        ->required(),

                    Forms\Components\Select::make('participant_payment_id')
                        ->label('Powiązany płatny uczestnik')
                        ->options(fn () => $this->getParticipantPaymentOptions())
                        ->searchable()
                        ->nullable()
                        ->helperText('Opcjonalnie: podepnij pozycję z rozliczenia. Bez rozliczenia kwota zostanie policzona jako koszt imprezy / liczba płatnych uczestników.'),

                    Forms\Components\TextInput::make('title')
                        ->label('Tytuł umowy')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('agreement_number')
                        ->label('Numer umowy')
                        ->maxLength(255)
                        ->helperText('Możesz pozostawić puste, numer zostanie nadany automatycznie.'),

                    Forms\Components\DatePicker::make('agreement_date')
                        ->label('Data umowy')
                        ->native(false)
                        ->default(now()),
                ]),

            Forms\Components\Section::make('Dane imprezy i klienta')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('event_name')
                        ->label('Nazwa imprezy')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('participant_count')
                        ->label('Liczba uczestników')
                        ->numeric()
                        ->minValue(1)
                        ->default(fn () => (int) ($this->getOwnerRecord()->participant_count ?? 1)),

                    Forms\Components\DatePicker::make('event_start_date')
                        ->label('Data rozpoczęcia')
                        ->native(false),

                    Forms\Components\DatePicker::make('event_end_date')
                        ->label('Data zakończenia')
                        ->native(false),

                    Forms\Components\TextInput::make('customer_name')
                        ->label('Klient / osoba kontaktowa')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('customer_email')
                        ->label('Email klienta')
                        ->email()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('customer_phone')
                        ->label('Telefon klienta')
                        ->maxLength(64),

                    Forms\Components\TextInput::make('participant_name')
                        ->label('Uczestnik (indywidualna)')
                        ->maxLength(255),

                    Forms\Components\DatePicker::make('participant_birth_date')
                        ->label('Data urodzenia uczestnika')
                        ->native(false),
                ]),

            Forms\Components\Section::make('Płatność')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('amount_due')
                        ->label('Kwota do zapłaty')
                        ->numeric()
                        ->default(fn () => (function () {
                            $event = $this->getOwnerRecord();
                            $count = max(1, (int) ($event->participant_count ?? 1));
                            return $event->resolvedPricePerPerson($count) * $count;
                        })())
                        ->required()
                        ->suffix('PLN')
                        ->helperText(fn (Get $get) => (function () use ($get): string {
                            $event = $this->getOwnerRecord();
                            $participantCount = max(1, (int) ($event->participant_count ?? 1));
                            $pricePerPerson = $event->resolvedPricePerPerson($participantCount);
                            $totalAmount = $pricePerPerson * $participantCount;
                            return sprintf(
                                'Kalkulacja: %s PLN/os. × %d os. = %s PLN',
                                number_format($pricePerPerson, 2, ',', ' '),
                                $participantCount,
                                number_format($totalAmount, 2, ',', ' ')
                            );
                        })()),

                    Forms\Components\TextInput::make('amount_paid')
                        ->label('Kwota opłacona')
                        ->numeric()
                        ->default(0)
                        ->suffix('PLN'),

                    Forms\Components\Select::make('currency')
                        ->label('Waluta')
                        ->options([
                            'PLN' => 'PLN',
                            'EUR' => 'EUR',
                            'USD' => 'USD',
                        ])
                        ->default('PLN')
                        ->required(),

                    Forms\Components\Select::make('status')
                        ->label('Status umowy')
                        ->options(EventAgreement::$statuses)
                        ->default('sent')
                        ->required(),

                    Forms\Components\Select::make('payment_status')
                        ->label('Status płatności')
                        ->options(EventAgreement::$paymentStatuses)
                        ->default('pending')
                        ->required(),

                    Forms\Components\Select::make('payment_method')
                        ->label('Metoda płatności')
                        ->options(EventAgreement::$paymentMethods)
                        ->nullable(),

                    Forms\Components\DateTimePicker::make('signed_at')
                        ->label('Data zawarcia')
                        ->nullable(),

                    Forms\Components\DateTimePicker::make('paid_at')
                        ->label('Data płatności')
                        ->nullable(),
                ]),

            Forms\Components\Section::make('Treść i załączniki')
                ->schema([
                    Forms\Components\Textarea::make('agreement_body')
                        ->label('Treść umowy')
                        ->rows(12)
                        ->columnSpanFull(),

                    Forms\Components\CheckboxList::make('selected_attachments')
                        ->label('Wybierz gotowe załączniki')
                        ->options(fn (): array => $this->getSelectableAttachmentOptions())
                        ->columns(1)
                        ->helperText('Możesz wybrać gotowe pliki z systemu i katalogu pliki oraz jednocześnie dodać własne pliki poniżej.')
                        ->default(fn (?EventAgreement $record): array => $this->resolveSelectedAttachmentDefaults($record?->attachments ?? [])),

                    Forms\Components\FileUpload::make('attachments')
                        ->label('Załączniki do umowy')
                        ->multiple()
                        ->disk('public')
                        ->directory('event-agreements')
                        ->preserveFilenames()
                        ->downloadable()
                        ->openable(),

                    Forms\Components\RichEditor::make('admin_notes')
                        ->label('Uwagi administratora')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(function (): string {
                $event = $this->getOwnerRecord();
                $report = $event->buildIndividualAgreementReport($event->agreements()->get());
                $summary = $report['summary'];

                return sprintf(
                    'Umowy i płatności • Indywidualne opłacone: %s • Pozostało: %s PLN',
                    $summary['payment_progress_label'],
                    number_format((float) $summary['amount_remaining'], 2, ',', ' ')
                );
            })
            ->columns([
                Tables\Columns\TextColumn::make('agreement_number')
                    ->label('Numer')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Umowa')
                    ->searchable()
                    ->wrap()
                    ->description(fn (EventAgreement $record) => $record->event_name ?: '—'),

                Tables\Columns\BadgeColumn::make('agreement_type')
                    ->label('Typ')
                    ->formatStateUsing(fn (EventAgreement $record) => $record->agreement_type_label)
                    ->colors([
                        'info' => EventAgreement::TYPE_GROUP,
                        'primary' => EventAgreement::TYPE_INDIVIDUAL,
                    ]),

                Tables\Columns\TextColumn::make('paid_user')
                    ->label('Użytkownik')
                    ->state(fn (EventAgreement $record) => $record->signer_name ?: $record->participant_name ?: $record->customer_name ?: '—')
                    ->description(fn (EventAgreement $record) => $record->signer_email ?: $record->customer_email ?: null)
                    ->searchable(['signer_name', 'participant_name', 'customer_name', 'signer_email', 'customer_email']),

                Tables\Columns\TextColumn::make('amount_due')
                    ->label('Do zapłaty')
                    ->money('PLN')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('payment_status')
                    ->label('Płatność')
                    ->formatStateUsing(fn ($state) => EventAgreement::$paymentStatuses[$state] ?? $state)
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'paid',
                        'danger' => 'failed',
                    ]),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Umowa')
                    ->formatStateUsing(fn ($state) => EventAgreement::$statuses[$state] ?? $state)
                    ->colors([
                        'gray' => 'draft',
                        'info' => 'sent',
                        'warning' => 'signed',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                    ]),

                Tables\Columns\TextColumn::make('public_link')
                    ->label('Link klienta')
                    ->state(fn (EventAgreement $record) => $record->public_link)
                    ->copyable()
                    ->copyMessage('Skopiowano link')
                    ->limit(32),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Opłacono')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('individual_agreements_report')
                    ->label('Raport umów indywidualnych')
                    ->icon('heroicon-o-chart-bar-square')
                    ->color('gray')
                    ->modalHeading('Raport zawartych umów indywidualnych')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Zamknij')
                    ->modalWidth('7xl')
                    ->modalContent(function () {
                        $event = $this->getOwnerRecord();
                        $report = $event->buildIndividualAgreementReport($event->agreements()->get());

                        return view('filament.event-resource.individual-agreement-report', [
                            'event' => $event,
                            'report' => $report,
                        ]);
                    }),

                Tables\Actions\Action::make('export_individual_agreements_csv')
                    ->label('Eksport CSV')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->url(fn () => route('admin.events.individual-agreements.export', [
                        'event' => $this->getOwnerRecord()->id,
                        'format' => 'csv',
                    ]))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('export_individual_agreements_xlsx')
                    ->label('Eksport XLSX')
                    ->icon('heroicon-o-table-cells')
                    ->color('info')
                    ->url(fn () => route('admin.events.individual-agreements.export', [
                        'event' => $this->getOwnerRecord()->id,
                        'format' => 'xlsx',
                    ]))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('generate_from_event')
                    ->label('Generuj umowę grupową')
                    ->icon('heroicon-o-bolt')
                    ->color('warning')
                    ->form([
                        Forms\Components\Select::make('contract_template_id')
                            ->label('Szablon umowy')
                            ->options(fn () => ContractTemplate::orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->nullable(),

                        Forms\Components\TextInput::make('title')
                            ->label('Tytuł umowy')
                            ->default('Umowa imprezy')
                            ->required(),

                        Forms\Components\TextInput::make('amount_due')
                            ->label('Kwota do zapłaty')
                            ->numeric()
                            ->default(fn () => (function () {
                                $event = $this->getOwnerRecord();
                                $count = max(1, (int) ($event->participant_count ?? 1));
                                return $event->resolvedPricePerPerson($count) * $count;
                            })())
                            ->required()
                            ->suffix('PLN')
                            ->helperText(fn () => (function () {
                                $event = $this->getOwnerRecord();
                                $count = max(1, (int) ($event->participant_count ?? 1));
                                $pricePerPerson = $event->resolvedPricePerPerson($count);
                                return sprintf(
                                    'Kalkulacja: %s PLN/os. × %d os.',
                                    number_format($pricePerPerson, 2, ',', ' '),
                                    $count
                                );
                            })()),

                        Forms\Components\TextInput::make('participant_count')
                            ->label('Liczba uczestników')
                            ->numeric()
                            ->default(fn () => (int) ($this->getOwnerRecord()->participant_count ?? 1))
                            ->minValue(1),

                        Forms\Components\CheckboxList::make('selected_attachments')
                            ->label('Załączniki do dołączenia')
                            ->options(fn (): array => $this->getSelectableAttachmentOptions())
                            ->columns(1)
                            ->helperText('Zaznaczone pliki z systemu lub katalogu pliki będą dostępne klientowi razem z umową.'),
                    ])
                    ->action(function (array $data): void {
                        $event = $this->getOwnerRecord();
                        $attachments = $this->resolveAttachmentsFromData($data);

                        $agreement = EventAgreement::create([
                            'event_id' => $event->id,
                            'contract_template_id' => $data['contract_template_id'] ?? null,
                            'agreement_type' => EventAgreement::TYPE_GROUP,
                            'title' => $data['title'],
                            'agreement_date' => now()->toDateString(),
                            'event_name' => $event->name,
                            'event_start_date' => $event->start_date,
                            'event_end_date' => $event->end_date,
                            'customer_name' => $event->client_name,
                            'customer_email' => $event->client_email,
                            'customer_phone' => $event->client_phone,
                            'participant_count' => (int) ($data['participant_count'] ?? $event->participant_count ?? 1),
                            'amount_due' => (float) ($data['amount_due'] ?? 0),
                            'currency' => 'PLN',
                            'status' => 'sent',
                            'payment_status' => 'pending',
                            'attachments' => $attachments,
                            'created_by' => auth()->id(),
                        ]);

                        $agreement->regenerateAgreementBody();

                        Notification::make()
                            ->title('Umowa wygenerowana')
                            ->body('Utworzono umowę i link do zawarcia dla klienta.')
                            ->success()
                            ->actions([
                                NotificationAction::make('open')
                                    ->label('Otwórz link klienta')
                                    ->url($agreement->public_link)
                                    ->openUrlInNewTab(),
                            ])
                            ->send();
                    }),

                Tables\Actions\Action::make('generate_individual_from_settlement')
                    ->label('Generuj umowy indywidualne')
                    ->icon('heroicon-o-users')
                    ->color('primary')
                    ->form([
                        Forms\Components\Select::make('contract_template_id')
                            ->label('Szablon umowy')
                            ->options(fn () => ContractTemplate::orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->nullable(),

                        Forms\Components\TextInput::make('title')
                            ->label('Tytuł umowy')
                            ->default('Umowa uczestnika')
                            ->required(),

                        Forms\Components\TextInput::make('paying_participants_count')
                            ->label('Liczba płatnych uczestników')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->default(fn () => max(1, (int) ($this->getOwnerRecord()->participant_count ?? 1)))
                            ->helperText('Dla każdego płatnego uczestnika zostanie wygenerowany osobny link do umowy.'),

                        Forms\Components\CheckboxList::make('selected_attachments')
                            ->label('Załączniki do dołączenia')
                            ->options(fn (): array => $this->getSelectableAttachmentOptions())
                            ->columns(1)
                            ->helperText('Zaznaczone pliki z systemu lub katalogu pliki trafią do szablonu umowy indywidualnej i do każdego klonu.'),
                    ])
                    ->action(function (array $data): void {
                        $event = $this->getOwnerRecord();
                        $payingParticipantsCount = max(1, (int) ($data['paying_participants_count'] ?? $event->participant_count ?? 1));
                        $amountPerPerson = $this->resolveIndividualAmountDueForEvent($payingParticipantsCount);
                        $attachments = $this->resolveAttachmentsFromData($data);

                        if ($amountPerPerson <= 0) {
                            Notification::make()
                                ->title('Brak kosztu imprezy')
                                ->body('Aby policzyć cenę za osobę, ustaw koszt imprezy większy od 0.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $existingTemplate = (int) $event->agreements()
                            ->where('agreement_type', EventAgreement::TYPE_INDIVIDUAL)
                            ->where('status', 'template')
                            ->count();

                        if ($existingTemplate > 0) {
                            Notification::make()
                                ->title('Szablon umowy indywidualnej już istnieje')
                                ->body("Już wygenerowałeś szablon. Wysyłaj jego link do uczestników.")
                                ->warning()
                                ->send();

                            return;
                        }

                        // Utwórz JEDEN szablon dla wszystkich N uczestników
                        $agreement = EventAgreement::create([
                            'event_id' => $event->id,
                            'contract_template_id' => $data['contract_template_id'] ?? null,
                            'agreement_type' => EventAgreement::TYPE_INDIVIDUAL,
                            'title' => ($data['title'] ?? 'Umowa uczestnika') . ' (szablon)',
                            'agreement_date' => now()->toDateString(),
                            'event_name' => $event->name,
                            'event_start_date' => $event->start_date,
                            'event_end_date' => $event->end_date,
                            'customer_name' => $event->client_name,
                            'customer_email' => $event->client_email,
                            'customer_phone' => $event->client_phone,
                            'participant_count' => 1,
                            'amount_due' => $amountPerPerson,
                            'currency' => 'PLN',
                            'status' => 'template',
                            'payment_status' => 'pending',
                            'attachments' => $attachments,
                            'created_by' => auth()->id(),
                            'meta' => ['is_individual_template' => true, 'expected_participants' => $payingParticipantsCount],
                        ]);

                        $agreement->regenerateAgreementBody();

                        Notification::make()
                            ->title('Szablon umowy indywidualnej wygenerowany')
                            ->body("Wysyłaj poniższy link do {$payingParticipantsCount} uczestników. Każdy wypełni formularz i zapłaci indywidualnie. Cena za osobę: " . number_format($amountPerPerson, 2, ',', ' ') . ' PLN.')
                            ->success()
                            ->actions([
                                \Filament\Notifications\Actions\Action::make('open')
                                    ->label('Otwórz link')
                                    ->url($agreement->public_link)
                                    ->openUrlInNewTab(),
                                \Filament\Notifications\Actions\Action::make('copy')
                                    ->label('Skopiuj link')
                                    ->close(),
                            ])
                            ->send();
                    }),

                Tables\Actions\CreateAction::make()
                    ->label('Nowa umowa')
                    ->mutateFormDataUsing(function (array $data): array {
                        $event = $this->getOwnerRecord();

                        $data = $this->mergeSelectedAttachmentsIntoData($data);

                        $data['event_id'] = $event->id;
                        $data['created_by'] = auth()->id();

                        $data['event_name'] = $data['event_name'] ?? $event->name;
                        $data['event_start_date'] = $data['event_start_date'] ?? optional($event->start_date)?->toDateString();
                        $data['event_end_date'] = $data['event_end_date'] ?? optional($event->end_date)?->toDateString();
                        $data['customer_name'] = $data['customer_name'] ?? $event->client_name;
                        $data['customer_email'] = $data['customer_email'] ?? $event->client_email;
                        $data['customer_phone'] = $data['customer_phone'] ?? $event->client_phone;

                        $data['agreement_type'] = $data['agreement_type'] ?? EventAgreement::TYPE_GROUP;

                        if (($data['agreement_type'] ?? null) === EventAgreement::TYPE_INDIVIDUAL) {
                            $data['participant_count'] = 1;
                        }

                        if (!empty($data['participant_payment_id'])) {
                            $participantPayment = EventSettlementParticipantPayment::query()->find($data['participant_payment_id']);

                            if ($participantPayment) {
                                $data['participant_name'] = $data['participant_name'] ?? $participantPayment->participant_name;

                                if (($data['agreement_type'] ?? null) === EventAgreement::TYPE_INDIVIDUAL && (!isset($data['amount_due']) || (float) $data['amount_due'] <= 0)) {
                                    $data['amount_due'] = (float) $participantPayment->due_amount_pln;
                                }
                            }
                        }

                        if (($data['agreement_type'] ?? null) === EventAgreement::TYPE_INDIVIDUAL && (!isset($data['amount_due']) || (float) $data['amount_due'] <= 0)) {
                            $data['amount_due'] = $this->resolveIndividualAmountDueForEvent(
                                max(1, (int) ($event->participant_count ?? 1))
                            );
                        }

                        if (($data['agreement_type'] ?? null) === EventAgreement::TYPE_GROUP && (!isset($data['amount_due']) || (float) $data['amount_due'] <= 0)) {
                            $participantCount = max(1, (int) ($data['participant_count'] ?? $event->participant_count ?? 1));
                            $data['amount_due'] = $event->resolvedPricePerPerson($participantCount) * $participantCount;
                        }

                        $data['status'] = $data['status'] ?? 'sent';
                        $data['payment_status'] = $data['payment_status'] ?? 'pending';

                        return $data;
                    })
                    ->after(function (EventAgreement $record): void {
                        if (blank($record->agreement_body)) {
                            $record->regenerateAgreementBody();
                        }

                        Notification::make()
                            ->title('Umowa utworzona')
                            ->body('Możesz już wysłać klientowi link do zawarcia umowy.')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('open_public')
                    ->label('Otwórz link')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (EventAgreement $record) => $record->public_link)
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('regenerate')
                    ->label('Regeneruj treść')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->action(function (EventAgreement $record): void {
                        $record->regenerateAgreementBody();

                        Notification::make()
                            ->title('Treść umowy zaktualizowana')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('mark_paid')
                    ->label('Oznacz jako opłacona')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (EventAgreement $record) => $record->payment_status !== 'paid')
                    ->action(function (EventAgreement $record): void {
                        $record->update([
                            'status' => 'completed',
                            'payment_status' => 'paid',
                            'amount_paid' => (float) $record->amount_due,
                            'paid_at' => now(),
                        ]);

                        app(AgreementPaymentSyncService::class)->sync($record->fresh());

                        Notification::make()
                            ->title('Umowa oznaczona jako opłacona')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(fn (array $data): array => $this->mergeSelectedAttachmentsIntoData($data)),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }

    protected function resolveIndividualAmountDueForEvent(int $payingParticipantsCount): float
    {
        $event = $this->getOwnerRecord();
        return $event->resolvedPricePerPerson($payingParticipantsCount);
    }

    protected function getSelectableAttachmentOptions(): array
    {
        return collect($this->getSelectableAttachmentCatalog())
            ->mapWithKeys(fn (array $item, string $path): array => [$path => $item['label']])
            ->all();
    }

    protected function resolveSelectedAttachmentDefaults(array $attachments): array
    {
        $selectable = array_keys($this->getSelectableAttachmentOptions());

        return array_values(array_intersect($attachments, $selectable));
    }

    protected function resolveAttachmentsFromData(array $data): array
    {
        $selected = $this->materializeSelectedAttachments(Arr::wrap($data['selected_attachments'] ?? []));

        return $this->mergeAttachmentLists(
            Arr::wrap($data['attachments'] ?? []),
            $selected,
        );
    }

    protected function mergeSelectedAttachmentsIntoData(array $data): array
    {
        $data['attachments'] = $this->resolveAttachmentsFromData($data);
        unset($data['selected_attachments']);

        return $data;
    }

    protected function mergeAttachmentLists(array $uploaded, array $selected): array
    {
        $normalized = array_merge($uploaded, $selected);
        $normalized = array_filter($normalized, fn ($path) => filled($path));

        return array_values(array_unique($normalized));
    }

    protected function getSelectableAttachmentCatalog(): array
    {
        $catalog = [];

        $publicFiles = [
            'dokumenty/Warunki-Uczestnictwa-2026.pdf' => 'System / Warunki uczestnictwa 2026',
            'dokumenty/Standardowy-Formularz.pdf' => 'System / Standardowy formularz informacyjny',
            'nnw_ow_rp.pdf' => 'System / Warunki ubezpieczenia NNW - kraj',
            'kl_ow.pdf' => 'System / Warunki ubezpieczenia KL',
            'kr_ow.pdf' => 'System / Warunki ubezpieczenia kosztów rezygnacji',
            'dokumenty/regulamin_przewozu_osób.pdf' => 'System / Regulamin przewozu osób',
            'dokumenty/polityka_rodo.pdf' => 'System / Polityka RODO',
            'dokumenty/wpis_do_rejestru_organizatorow.pdf' => 'System / Wpis do rejestru organizatorów',
        ];

        foreach ($publicFiles as $path => $label) {
            if (Storage::disk('public')->exists($path)) {
                $catalog[$path] = [
                    'label' => $label,
                    'type' => 'public',
                ];
            }
        }

        $workspaceFilesDir = base_path('pliki');

        if (File::isDirectory($workspaceFilesDir)) {
            foreach (File::allFiles($workspaceFilesDir) as $file) {
                $extension = strtolower($file->getExtension());

                if (!$this->isAllowedWorkspaceAttachmentExtension($extension)) {
                    continue;
                }

                $relativePath = str_replace($workspaceFilesDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $relativePath = str_replace('\\', '/', $relativePath);
                $publicPath = $this->mapWorkspaceAttachmentToPublicPath($relativePath);

                $catalog[$publicPath] = [
                    'label' => 'Pliki / ' . str_replace('/', ' / ', $relativePath),
                    'type' => 'workspace',
                    'source_path' => $file->getPathname(),
                ];
            }
        }

        ksort($catalog);

        return $catalog;
    }

    protected function materializeSelectedAttachments(array $selected): array
    {
        $catalog = $this->getSelectableAttachmentCatalog();
        $resolved = [];

        foreach ($selected as $path) {
            if (!is_string($path) || $path === '') {
                continue;
            }

            $item = $catalog[$path] ?? null;

            if (($item['type'] ?? null) === 'workspace' && !empty($item['source_path'])) {
                $this->copyWorkspaceAttachmentToPublic($item['source_path'], $path);
            }

            $resolved[] = $path;
        }

        return $resolved;
    }

    protected function copyWorkspaceAttachmentToPublic(string $sourcePath, string $destinationPath): void
    {
        if (Storage::disk('public')->exists($destinationPath)) {
            return;
        }

        Storage::disk('public')->put($destinationPath, File::get($sourcePath));
    }

    protected function isAllowedWorkspaceAttachmentExtension(string $extension): bool
    {
        return in_array($extension, ['pdf', 'doc', 'docx', 'rtf', 'txt', 'jpg', 'jpeg', 'png', 'webp'], true);
    }

    protected function mapWorkspaceAttachmentToPublicPath(string $relativePath): string
    {
        $relativePath = trim($relativePath, '/');
        $directory = str_replace('\\', '/', dirname($relativePath));
        $directory = $directory === '.' ? '' : collect(explode('/', $directory))
            ->filter(fn ($segment) => $segment !== '')
            ->map(fn ($segment) => Str::slug($segment))
            ->implode('/');

        $fileName = pathinfo($relativePath, PATHINFO_FILENAME);
        $extension = strtolower((string) pathinfo($relativePath, PATHINFO_EXTENSION));
        $slug = Str::slug($fileName);
        $hash = substr(sha1($relativePath), 0, 8);

        $targetName = trim($slug !== '' ? $slug : 'plik', '-') . '-' . $hash . ($extension !== '' ? '.' . $extension : '');

        return trim('event-agreements/library/' . ($directory !== '' ? $directory . '/' : '') . $targetName, '/');
    }

    protected function getParticipantPaymentOptions(): array
    {
        $event = $this->getOwnerRecord();
        $settlement = $event->activeSettlement;

        if (!$settlement) {
            return [];
        }

        return $settlement->participantPayments()
            ->orderBy('participant_name')
            ->get()
            ->mapWithKeys(function (EventSettlementParticipantPayment $payment): array {
                $amount = number_format((float) $payment->due_amount_pln, 2, ',', ' ');
                $label = $payment->participant_name . " ({$amount} PLN)";

                return [$payment->id => $label];
            })
            ->all();
    }
}
