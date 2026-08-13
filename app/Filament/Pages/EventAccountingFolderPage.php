<?php

namespace App\Filament\Pages;

use App\Models\Event;
use App\Services\EventAccountingExportService;
use App\Services\EventInvoicePdfMergeService;
use App\Support\FilamentNavigation;
use App\Support\FinanceModuleNavigation;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EventAccountingFolderPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-folder-arrow-down';

    protected static string $view = 'filament.pages.event-accounting-folder';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_FINANCE;

    protected static ?string $navigationLabel = 'Teczka księgowa';

    protected static ?int $navigationSort = 12;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public ?int $eventId = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && ($user->hasRole(['admin', 'super_admin', 'ksiegowosc']) || $user->can('view_any_event::settlement'));
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function getTitle(): string
    {
        return 'Teczka dla księgowej';
    }

    public function getNavigationTabs(): array
    {
        return FinanceModuleNavigation::tabs('accounting-folder');
    }

    protected function getForms(): array
    {
        return [
            'form' => $this->makeForm()
                ->schema([
                    Select::make('eventId')
                        ->label('Impreza')
                        ->searchable()
                        ->required()
                        ->options(fn (): array => Event::query()
                            ->orderByDesc('start_date')
                            ->limit(500)
                            ->get()
                            ->mapWithKeys(fn (Event $event): array => [
                                $event->id => trim(($event->code ?: '#'.$event->id).' — '.$event->name),
                            ])
                            ->all()),
                ])
                ->statePath(''),
        ];
    }

    public function downloadArchive(): ?BinaryFileResponse
    {
        if (! $this->eventId) {
            Notification::make()->title('Wybierz imprezę')->warning()->send();

            return null;
        }

        $event = Event::query()->find($this->eventId);

        if (! $event) {
            Notification::make()->title('Nie znaleziono imprezy')->danger()->send();

            return null;
        }

        try {
            $archive = app(EventAccountingExportService::class)->buildArchive($event);

            return response()->download($archive['path'], $archive['filename'])->deleteFileAfterSend(true);
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Nie udało się wygenerować paczki')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return null;
        }
    }

    public function downloadInvoicesPdf(): ?BinaryFileResponse
    {
        if (! $this->eventId) {
            Notification::make()->title('Wybierz imprezę')->warning()->send();

            return null;
        }

        $event = Event::query()->find($this->eventId);

        if (! $event) {
            Notification::make()->title('Nie znaleziono imprezy')->danger()->send();

            return null;
        }

        try {
            $result = app(EventInvoicePdfMergeService::class)->mergeToTempFile($event);

            return response()->download($result['path'], $result['filename'])->deleteFileAfterSend(true);
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Nie udało się wygenerować zbiorczego PDF faktur')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return null;
        }
    }
}
