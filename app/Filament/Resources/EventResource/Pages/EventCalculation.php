<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventFinanceSubNavigation;
use App\Filament\Resources\EventResource\Concerns\InteractsWithEventRecord;
use App\Services\EventPriceCalculator;
use Filament\Actions;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\Page;

class EventCalculation extends Page
{
    use HasEventFinanceSubNavigation;
    use InteractsWithEventRecord;

    protected static string $resource = EventResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.event-calculation';

    protected static ?string $navigationLabel = 'Kalkulacja';

    protected static ?string $title = 'Kalkulacja';

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(static::getResource()::canEdit($this->getRecord()), 403);
        $this->record->load(['bus', 'markup', 'programPoints.templatePoint']);

        try {
            $hasVariants = $this->record->qtyVariants()->exists();
        } catch (\Throwable $e) {
            $hasVariants = false;
        }

        if (! $hasVariants) {
            $this->redirect(EventResource::getUrl('finance', ['record' => $this->record]));

            return;
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('recalculate_event')
                ->label('Przelicz')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->tooltip('Przelicza koszty i ceny imprezy na podstawie programu i transportu.')
                ->requiresConfirmation()
                ->action(function () {
                    (new EventPriceCalculator)->calculateForEvent($this->record);
                    \Filament\Notifications\Notification::make()->title('Kalkulacja wykonana')->success()->send();
                    $this->dispatch('event-price-table-refresh');
                }),
            ActionGroup::make([
                Actions\Action::make('create_snapshot')
                    ->label('Zapisz migawkę')
                    ->icon('heroicon-o-camera')
                    ->tooltip('Zapisuje aktualny stan kalkulacji do porównania później.')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('name')
                            ->label('Nazwa migawki')
                            ->required()
                            ->default('Migawka kalkulacji '.now()->format('d.m.Y H:i')),
                        \FilamentTiptapEditor\TiptapEditor::make('description')
                            ->label('Opis')
                            ->maxLength(500),
                    ])
                    ->action(function (array $data) {
                        $this->record->createManualSnapshot($data['name'], $data['description'] ?? null);
                        \Filament\Notifications\Notification::make()->title('Migawka zapisana')->success()->send();
                    }),
                Actions\Action::make('export_pdf')
                    ->label('Pobierz PDF')
                    ->tooltip('Pobierz kalkulację w formacie PDF.')
                    ->url(fn () => route('admin.events.calculation.pdf', $this->record))
                    ->openUrlInNewTab(),
                Actions\Action::make('export_excel')
                    ->label('Pobierz Excel')
                    ->tooltip('Pobierz kalkulację w arkuszu Excel.')
                    ->url(fn () => route('admin.events.calculation.excel', $this->record))
                    ->openUrlInNewTab(),
            ])
                ->label('Więcej')
                ->icon('heroicon-o-ellipsis-vertical')
                ->color('gray')
                ->button(),
        ];
    }

    public function getWidgets(): array
    {
        return [
            \App\Filament\Resources\EventResource\Widgets\EventPriceTable::class,
        ];
    }

    public static function getResourcePageName(): string
    {
        foreach (EventResource::getPages() as $pageName => $pageRegistration) {
            if ($pageRegistration->getPage() !== static::class) {
                continue;
            }

            return $pageName;
        }

        throw new \Exception('Page ['.static::class.'] is not registered to the resource ['.EventResource::class.'].');
    }

    public static function getRouteName(?string $panel = null): string
    {
        return EventResource::getRouteBaseName(panel: $panel).'.'.static::getResourcePageName();
    }
}
