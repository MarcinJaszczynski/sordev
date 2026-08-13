<?php

declare(strict_types=1);

namespace App\Filament\Resources\ContractResource\Concerns;

use App\Filament\Forms\ContractAnnexFields;
use App\Filament\Forms\ContractTfgForm;
use App\Actions\Finance\ApplyPaymentScheduleTemplateAction;
use App\Actions\Finance\CaptureContractScheduleAsLibraryTemplateAction;
use App\Jobs\Tfg\SubmitTfgFeedJob;
use App\Models\Contract;
use App\Models\PaymentScheduleTemplate;
use App\Services\ContractPaymentSyncService;
use App\Services\ContractTfgSetupService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Schema;

/**
 * Operacje na umowie (PDF, aneks, TFG, status) — kanonicznie na stronie edycji, nie w tabeli imprezy.
 */
trait ManagesContractEditHeaderActions
{
    /**
     * @return array<int, Actions\Action|Actions\ActionGroup>
     */
    protected function contractOperationalHeaderActions(): array
    {
        return [
            Actions\Action::make('open_public')
                ->label('Link klienta')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (): string => $this->getRecord()->public_link)
                ->openUrlInNewTab(),

            Actions\Action::make('download_pdf')
                ->label('PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->url(fn (): string => route('admin.contracts.agreement-pdf', ['contract' => $this->getRecord()]))
                ->openUrlInNewTab()
                ->visible(fn (): bool => filled($this->getRecord()->agreement_body)
                    || $this->getRecord()->usesUploadedAgreementDocument()),

            Actions\ActionGroup::make([
                Actions\Action::make('regenerate')
                    ->label('Regeneruj treść')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (): bool => $this->getRecord()->shouldAutoGenerateAgreementBody())
                    ->action(function (): void {
                        $this->getRecord()->regenerateAgreementBody();
                        Notification::make()->title('Treść umowy zaktualizowana')->success()->send();
                    }),

                Actions\Action::make('sync_group_participant_payments')
                    ->label('Synchronizuj wpłaty uczestników')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (): bool => $this->getRecord()->usesIndividualParticipantPayments())
                    ->action(function (): void {
                        app(ContractPaymentSyncService::class)->sync($this->getRecord()->fresh());
                        Notification::make()->title('Wpłaty uczestników zsynchronizowane')->success()->send();
                    }),

                Actions\Action::make('apply_payment_schedule_template')
                    ->label('Zastosuj szablon harmonogramu')
                    ->icon('heroicon-o-calendar-days')
                    ->visible(fn (): bool => Schema::hasTable('payment_schedule_templates')
                        && $this->getRecord()->event_id
                        && $this->getRecord()->status !== 'cancelled')
                    ->form([
                        Forms\Components\Select::make('payment_schedule_template_id')
                            ->label('Szablon')
                            ->options(fn (): array => PaymentScheduleTemplate::optionsForSelect(
                                $this->getRecord()->contract_type ?? $this->getRecord()->agreement_type
                            ))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $contract = $this->getRecord()->fresh();
                        try {
                            app(ApplyPaymentScheduleTemplateAction::class)(
                                (int) $data['payment_schedule_template_id'],
                                $contract,
                                $contract->event,
                            );
                            Notification::make()->title('Zastosowano szablon harmonogramu')->success()->send();
                            $this->refreshFormData(['payment_scheme', 'payment_schedule_template_id']);
                        } catch (\InvalidArgumentException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),

                Actions\Action::make('capture_schedule_to_library')
                    ->label('Zapisz raty do biblioteki')
                    ->icon('heroicon-o-bookmark')
                    ->visible(fn (): bool => Schema::hasTable('payment_schedule_templates')
                        && $this->getRecord()->paymentSchedules()->exists())
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->label('Nazwa szablonu')
                            ->required()
                            ->default(fn (): string => sprintf(
                                'Harmonogram z %s',
                                $this->getRecord()->contract_number ?: ('umowy #'.$this->getRecord()->id),
                            )),
                        Forms\Components\CheckboxList::make('applies_to')
                            ->label('Dotyczy typów')
                            ->options(PaymentScheduleTemplate::$appliesToOptions)
                            ->columns(3),
                    ])
                    ->action(function (array $data): void {
                        try {
                            $template = app(CaptureContractScheduleAsLibraryTemplateAction::class)(
                                $this->getRecord()->fresh(),
                                (string) $data['name'],
                                is_array($data['applies_to'] ?? null) ? $data['applies_to'] : null,
                            );
                            Notification::make()
                                ->title('Zapisano w bibliotece: '.$template->name)
                                ->success()
                                ->send();
                        } catch (\InvalidArgumentException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),

                Actions\Action::make('mark_paid')
                    ->label('Oznacz jako opłacona')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => $this->getRecord()->payment_status !== 'paid')
                    ->action(function (): void {
                        $record = $this->getRecord();
                        $record->update([
                            'status' => 'completed',
                            'payment_status' => 'paid',
                            'amount_paid' => (float) $record->amount_due,
                            'paid_at' => now(),
                        ]);
                        app(ContractPaymentSyncService::class)->sync($record->fresh());
                        Notification::make()->title('Umowa oznaczona jako opłacona')->success()->send();
                    }),

                Actions\Action::make('create_annex')
                    ->label('Utwórz aneks')
                    ->icon('heroicon-o-document-plus')
                    ->color('info')
                    ->visible(fn (): bool => ! $this->getRecord()->isAnnex()
                        && $this->getRecord()->status !== 'template')
                    ->form([
                        Forms\Components\TextInput::make('title')
                            ->label('Tytuł aneksu')
                            ->required()
                            ->default(fn (): string => sprintf(
                                'Aneks do umowy %s',
                                $this->getRecord()->contract_number ?: ('#'.$this->getRecord()->id),
                            )),
                        Forms\Components\DatePicker::make('agreement_date')
                            ->label('Data aneksu')
                            ->default(now())
                            ->required()
                            ->native(false),
                        Forms\Components\TextInput::make('amount_due')
                            ->label('Kwota aneksu')
                            ->numeric()
                            ->default(fn (): float => (float) $this->getRecord()->amount_due)
                            ->required()
                            ->suffix('PLN'),
                        Forms\Components\TextInput::make('participant_count')
                            ->label('Liczba uczestników')
                            ->numeric()
                            ->minValue(1)
                            ->default(fn (): int => (int) ($this->getRecord()->participant_count ?? 1)),
                        Forms\Components\DatePicker::make('event_start_date')
                            ->label('Data rozpoczęcia')
                            ->default(fn () => $this->getRecord()->event_start_date)
                            ->native(false),
                        Forms\Components\DatePicker::make('event_end_date')
                            ->label('Data zakończenia')
                            ->default(fn () => $this->getRecord()->event_end_date)
                            ->native(false),
                        ...ContractAnnexFields::createSchema(),
                        ...ContractTfgForm::schema(false, fn () => $this->getRecord()->event),
                    ])
                    ->fillForm(fn (): array => array_merge(
                        app(ContractTfgSetupService::class)->defaultsFromContract($this->getRecord()),
                        [
                            'body_edit_mode' => $this->getRecord()->body_edit_mode ?? Contract::BODY_EDIT_TEMPLATE,
                            'annex_change_types' => $this->getRecord()->annex_change_types ?? [],
                            'annex_program_change_notes' => $this->getRecord()->annex_program_change_notes,
                        ],
                    ))
                    ->action(function (array $data): void {
                        $annex = app(ContractTfgSetupService::class)->createAnnex($this->getRecord(), $data);
                        Notification::make()
                            ->title('Aneks utworzony')
                            ->body('Możesz wysłać klientowi nowy link do aneksu.')
                            ->success()
                            ->actions([
                                NotificationAction::make('open')
                                    ->label('Otwórz link aneksu')
                                    ->url($annex->public_link)
                                    ->openUrlInNewTab(),
                            ])
                            ->send();
                    }),

                Actions\Action::make('cancel_contract')
                    ->label('Anuluj umowę')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => ! in_array($this->getRecord()->status, ['cancelled', 'template'], true))
                    ->action(function (): void {
                        $record = $this->getRecord();
                        Contract::withoutEvents(function () use ($record): void {
                            $record->update(['status' => 'cancelled']);
                        });
                        app(ContractPaymentSyncService::class)->remove($record->fresh());
                        Notification::make()->title('Umowa anulowana')->success()->send();
                    }),

                Actions\Action::make('reopen_contract')
                    ->label('Przywróć umowę')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => $this->getRecord()->status === 'cancelled')
                    ->action(function (): void {
                        $this->getRecord()->update(['status' => 'sent']);
                        Notification::make()->title('Umowa przywrócona')->success()->send();
                    }),

                Actions\Action::make('submit_tfg')
                    ->label('Wyślij do TFG')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->visible(fn (): bool => $this->getRecord()->canSubmitNewData()
                        && blank($this->getRecord()->pending_operation))
                    ->requiresConfirmation()
                    ->action(function (): void {
                        $this->getRecord()->queueTfgOperation(Contract::OP_NOWEDANE);
                        SubmitTfgFeedJob::dispatch([$this->getRecord()->id]);
                        Notification::make()->title('Umowa w kolejce do TFG')->success()->send();
                    }),

                Actions\Action::make('correct_tfg')
                    ->label('Koryguj w TFG')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (): bool => $this->getRecord()->canCorrect())
                    ->form([
                        Forms\Components\Select::make('correction_reason')
                            ->label('Powód korekty')
                            ->options(config('tfg.correction_reasons'))
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $this->getRecord()->queueTfgOperation(Contract::OP_KOREKTA, $data['correction_reason']);
                        SubmitTfgFeedJob::dispatch([$this->getRecord()->id]);
                        Notification::make()->title('Korekta w kolejce do TFG')->success()->send();
                    }),

                Actions\Action::make('terminate_tfg')
                    ->label('Rozwiąż w TFG')
                    ->icon('heroicon-o-no-symbol')
                    ->color('warning')
                    ->visible(fn (): bool => $this->getRecord()->canTerminateOrDelete())
                    ->requiresConfirmation()
                    ->action(function (): void {
                        $this->getRecord()->queueTfgOperation(Contract::OP_ROZWIAZANIE);
                        SubmitTfgFeedJob::dispatch([$this->getRecord()->id]);
                        Notification::make()->title('Rozwiązanie w kolejce do TFG')->success()->send();
                    }),

                Actions\Action::make('delete_tfg')
                    ->label('Usuń w TFG')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (): bool => $this->getRecord()->canTerminateOrDelete())
                    ->requiresConfirmation()
                    ->action(function (): void {
                        $this->getRecord()->queueTfgOperation(Contract::OP_USUNIECIE);
                        SubmitTfgFeedJob::dispatch([$this->getRecord()->id]);
                        Notification::make()->title('Usunięcie w kolejce do TFG')->success()->send();
                    }),
            ])
                ->label('Operacje')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->button(),

            Actions\DeleteAction::make(),
        ];
    }
}
