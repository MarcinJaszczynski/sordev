<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Pilot\Resources\PilotEventResource;
use App\Filament\Resources\ChecklistTemplateResource;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventWorkflowContext;
use App\Models\User;
use App\Services\PilotAdvanceService;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class ManageEventPilot extends EditRecord
{
    use HasEventWorkflowContext;

    protected static string $resource = EventResource::class;

    protected static ?string $navigationLabel = 'Pilot';

    protected static ?string $title = 'Pilot i checklista';

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static string $view = 'filament.resources.event-resource.pages.manage-event-pilot';

    protected bool $pendingPilotPaymentApproval = false;

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Podgląd portalu pilota')
                ->icon('heroicon-o-eye')
                ->description('Otwórz widok imprezy tak, jak zobaczy ją przypisany pilot.')
                ->schema([
                    Forms\Components\Placeholder::make('pilot_preview_link')
                        ->hiddenLabel()
                        ->content(fn (): \Illuminate\Support\HtmlString => new \Illuminate\Support\HtmlString(
                            '<a href="'.e(PilotEventResource::getUrl(
                                'view',
                                ['record' => $this->record->getKey()],
                                panel: 'pilot',
                            ).'?preview=1').'" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-700">'
                            .'<span>Podgląd panelu pilota</span>'
                            .'</a>'
                            .(filled($this->record->assigned_to)
                                ? ''
                                : '<p class="mt-2 text-xs text-gray-500">Pilot nie jest jeszcze przypisany — podgląd pokazuje widok biura.</p>')
                        )),
                ]),

            ...\App\Filament\Forms\EventReadinessFields::pilotSection(),
        ]);
    }

    public function getContentTabLabel(): ?string
    {
        return 'Pilot';
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Zapisano dane pilota';
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->record->loadMissing('assignedUser');
        $data['pilot_birth_date'] = $this->record->assignedUser?->birth_date?->format('Y-m-d');
        $data['pilot_pesel'] = $this->record->assignedUser?->pesel;
        $data['pilot_phone'] = $this->record->assignedUser?->phone;
        $data['pilot_advance_planned_lines'] = app(PilotAdvanceService::class)->plannedLinesFormState($this->record);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (Schema::hasColumn('events', 'shared_with_pilot')) {
            $previousPilot = (int) ($this->record->assigned_to ?? 0);
            $newPilot = (int) ($data['assigned_to'] ?? 0);

            if ($previousPilot !== $newPilot) {
                $data['shared_with_pilot'] = false;
                $data['shared_with_pilot_at'] = null;
                $data['shared_with_pilot_by'] = null;

                if (Schema::hasColumn('events', 'pilot_trip_email_sent_at')) {
                    $data['pilot_trip_email_sent_at'] = null;
                }
            }
        }

        if (Schema::hasColumn('events', 'pilot_advance_planned_amount') && ! $this->record->pilot_funds_paid) {
            if (Schema::hasTable('pilot_advance_lines')) {
                unset($data['pilot_advance_planned_amount'], $data['pilot_advance_planned_at'], $data['pilot_advance_planned_by']);
            } else {
                $planned = $data['pilot_advance_planned_amount'] ?? null;
                $planned = $planned !== null && $planned !== '' ? round((float) $planned, 2) : null;
                $data['pilot_advance_planned_amount'] = $planned;

                if ($planned !== null && $planned > 0 && ! $this->record->pilot_advance_planned_at) {
                    $data['pilot_advance_planned_at'] = now();
                    $data['pilot_advance_planned_by'] = Auth::id();
                } elseif ($planned === null) {
                    $data['pilot_advance_planned_at'] = null;
                    $data['pilot_advance_planned_by'] = null;
                }
            }
        } elseif ($this->record->pilot_funds_paid) {
            unset($data['pilot_advance_planned_amount']);
        }

        unset($data['pilot_advance_planned_lines']);

        if (Schema::hasColumn('events', 'pilot_funds_paid')) {
            $this->pendingPilotPaymentApproval = ! empty($data['pilot_funds_paid']) && ! $this->record->pilot_funds_paid;
            unset($data['pilot_funds_paid']);
        }

        unset($data['pilot_birth_date'], $data['pilot_pesel'], $data['pilot_phone']);

        return $data;
    }

    protected function afterSave(): void
    {
        $state = $this->form->getState();

        if (Schema::hasTable('pilot_advance_lines') && ! $this->record->pilot_funds_paid) {
            app(PilotAdvanceService::class)->syncPlannedLines(
                $this->record->fresh(),
                $state['pilot_advance_planned_lines'] ?? [],
            );
        }

        if ($this->pendingPilotPaymentApproval) {
            $advanceService = app(PilotAdvanceService::class);
            $plannedLines = $state['pilot_advance_planned_lines'] ?? null;

            if (Schema::hasTable('pilot_advance_lines') && is_array($plannedLines) && $plannedLines !== []) {
                $advanceService->approvePayment(
                    $this->record->fresh(),
                    comment: $state['pilot_advance_paid_comment'] ?? null,
                    paidLines: collect($plannedLines)->map(fn (array $line) => [
                        'amount' => $line['amount'] ?? 0,
                        'currency_id' => (int) ($line['currency_id'] ?? 0),
                    ])->all(),
                );
            } else {
                $advanceService->approvePayment(
                    $this->record->fresh(),
                    isset($state['pilot_advance_paid_amount']) ? (float) $state['pilot_advance_paid_amount'] : null,
                    $state['pilot_advance_paid_currency_id'] ?? null,
                    $state['pilot_advance_paid_comment'] ?? null,
                );
            }

            $this->pendingPilotPaymentApproval = false;
        }

        User::syncPilotDemographics(
            $this->record->assigned_to,
            $state['pilot_birth_date'] ?? null,
            $state['pilot_pesel'] ?? null,
            $state['pilot_phone'] ?? null,
        );

        $this->record->refresh();

        if (
            Schema::hasColumn('events', 'shared_with_pilot')
            && filled($this->record->assigned_to)
            && ! $this->record->shared_with_pilot
        ) {
            Notification::make()
                ->title('Pilot przypisany — udostępnij wycieczkę')
                ->body('Kliknij «Udostępnij pilotowi» w sekcji Pilot powyżej. Bez tego pilot zobaczy 403 w panelu /pilot.')
                ->warning()
                ->persistent()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create_checklist_template')
                ->label('Utwórz szablon checklisty')
                ->icon('heroicon-o-plus-circle')
                ->color('gray')
                ->url(fn (): string => ChecklistTemplateResource::getUrl('create'))
                ->openUrlInNewTab()
                ->visible(fn (): bool => (bool) Auth::user()?->hasRole(['admin', 'super_admin', 'biuro'])),

            Actions\Action::make('manage_checklist_templates')
                ->label('Szablony checklisty')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('gray')
                ->url(fn (): string => ChecklistTemplateResource::getUrl('index'))
                ->openUrlInNewTab()
                ->visible(fn (): bool => (bool) Auth::user()?->hasRole(['admin', 'super_admin', 'biuro'])),

            Actions\Action::make('preview_pilot_advance')
                ->label('Podgląd zaliczki pilota')
                ->icon('heroicon-o-banknotes')
                ->color('gray')
                ->url(fn (): string => \App\Filament\Pilot\Pages\PilotAdvancePage::urlFor($this->record).'?preview=1')
                ->openUrlInNewTab()
                ->visible(fn (): bool => (bool) Auth::user()?->hasRole(['admin', 'super_admin', 'biuro'])),

            Actions\Action::make('preview_pilot_panel')
                ->label('Podgląd panelu pilota')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn (): string => PilotEventResource::getUrl(
                    'view',
                    ['record' => $this->record->getKey()],
                    panel: 'pilot',
                ).'?preview=1')
                ->openUrlInNewTab()
                ->visible(fn (): bool => (bool) Auth::user()?->hasRole(['admin', 'super_admin', 'biuro'])),

            Actions\Action::make('pdf_pilot')
                ->label('Pakiet pilota')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->tooltip('Załączniki dodasz w zakładce „Dokumenty”: zaznacz pakiety PDF i status „Zaakceptowany”.')
                ->url(fn () => route('admin.events.pdf', ['event' => $this->record->id, 'audience' => 'pilot']))
                ->openUrlInNewTab(),
        ];
    }
}
