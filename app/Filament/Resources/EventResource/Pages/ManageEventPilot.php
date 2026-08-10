<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Pilot\Resources\PilotEventResource;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventOperationsSubNavigation;
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
    use HasEventOperationsSubNavigation;
    use HasEventWorkflowContext;

    protected static string $resource = EventResource::class;

    protected static ?string $navigationLabel = 'Pilot';

    protected static ?string $title = 'Pilot';

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static string $view = 'filament.resources.event-resource.pages.manage-event-pilot';

    protected bool $pendingPilotPaymentApproval = false;

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Placeholder::make('pilot_empty_state')
                ->hiddenLabel()
                ->visible(fn (): bool => blank($this->record->assigned_to))
                ->content(new \Illuminate\Support\HtmlString(
                    '<div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-center dark:border-gray-600 dark:bg-gray-800/50">'
                    .'<p class="text-sm font-medium text-gray-900 dark:text-gray-100">Brak przypisanego pilota</p>'
                    .'<p class="mt-1 text-sm text-gray-500">Wybierz pilota w sekcji „Przypisanie” poniżej.</p>'
                    .'</div>'
                ))
                ->columnSpanFull(),

            ...\App\Filament\Forms\EventReadinessFields::pilotPageSchema(),
        ]);
    }

    protected function pilotPreviewUrl(): string
    {
        $url = PilotEventResource::getUrl(
            'view',
            ['record' => $this->record->getKey()],
            panel: 'pilot',
        ).'?preview=1';

        if (filled($this->record->assigned_to)) {
            $url .= '&pilot='.(int) $this->record->assigned_to;
        }

        return $url;
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
            Actions\Action::make('preview_pilot_panel')
                ->label('Podgląd portalu')
                ->icon('heroicon-o-eye')
                ->color('primary')
                ->url(fn (): string => $this->pilotPreviewUrl())
                ->openUrlInNewTab()
                ->visible(fn (): bool => (bool) Auth::user()?->hasRole(['admin', 'super_admin', 'biuro'])),

            Actions\Action::make('pdf_pilot')
                ->label('PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->url(fn () => route('admin.events.pdf', ['event' => $this->record->id, 'audience' => 'pilot']))
                ->openUrlInNewTab(),
        ];
    }
}
