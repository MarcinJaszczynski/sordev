<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Concerns\SingleRelationManagerPage;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventDocumentsSubNavigation;
use App\Filament\Resources\EventResource\Concerns\HasEventWorkflowContext;
use App\Filament\Resources\EventResource\RelationManagers\DocumentsRelationManager;
use App\Models\EventDocument;
use App\Models\EventPackageDocument;
use App\Services\EventPackageDocumentService;
use Filament\Actions;
use Filament\Forms;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Schema;

class ManageEventDocuments extends SingleRelationManagerPage
{
    use HasEventDocumentsSubNavigation;
    use HasEventWorkflowContext;

    protected static string $resource = EventResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.manage-event-documents';

    protected static ?string $navigationLabel = 'Dokumenty';

    /** H1 = aktywna sekcja nested (primary: Dokumenty). */
    protected static ?string $title = 'Pliki';

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    public ?string $packageActionAudience = null;

    protected static function relationManager(): string
    {
        return DocumentsRelationManager::class;
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($message = session()->pull('error')) {
            Notification::make()
                ->title('Nie można wygenerować dokumentu')
                ->body((string) $message)
                ->warning()
                ->send();
        }
    }

    public function booted(): void
    {
        $this->cacheAction($this->makeEditPackageAction());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getPackageCards(): array
    {
        /** @var EventPackageDocumentService $service */
        $service = app(EventPackageDocumentService::class);
        $cards = [];

        foreach (EventPackageDocument::AUDIENCES as $audience) {
            $package = $service->find($this->record, $audience);
            $cards[] = [
                'audience' => $audience,
                'label' => EventPackageDocument::$audienceLabels[$audience],
                'edit_mode' => $package?->edit_mode ?? EventPackageDocument::EDIT_LIVE,
                'edit_mode_label' => EventPackageDocument::$editModeLabels[$package?->edit_mode ?? EventPackageDocument::EDIT_LIVE],
                'status' => $package?->status ?? EventPackageDocument::STATUS_DRAFT,
                'status_label' => EventPackageDocument::$statusLabels[$package?->status ?? EventPackageDocument::STATUS_DRAFT],
                'download_url' => route('admin.events.pdf', [
                    'event' => $this->record->id,
                    'audience' => $audience,
                ]),
                'preview_url' => route('admin.events.pdf', [
                    'event' => $this->record->id,
                    'audience' => $audience,
                    'preview' => 1,
                ]),
            ];
        }

        return $cards;
    }

    /**
     * @return \Illuminate\Support\Collection<int, EventDocument>
     */
    public function getOfferDocuments()
    {
        return $this->record->documents()
            ->where('is_offer', true)
            ->orderByDesc('created_at')
            ->get();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('invoices_pdf')
                ->label('Wszystkie faktury (PDF)')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->tooltip('Scala faktury KSeF, dokumenty rozliczenia i pliki oznaczone jako faktura.')
                ->url(fn () => route('admin.events.invoices.pdf', ['event' => $this->record->id]))
                ->openUrlInNewTab(),
        ];
    }

    public function openEditPackage(string $audience): void
    {
        abort_unless(in_array($audience, EventPackageDocument::AUDIENCES, true), 404);
        $this->packageActionAudience = $audience;
        $this->mountAction('editPackage');
    }

    public function refreshPackage(string $audience): void
    {
        abort_unless(in_array($audience, EventPackageDocument::AUDIENCES, true), 404);

        app(EventPackageDocumentService::class)->refreshToLive($this->record, $audience);

        Notification::make()
            ->title('Przywrócono dane z imprezy')
            ->success()
            ->send();
    }

    public function freezePackage(string $audience): void
    {
        abort_unless(in_array($audience, EventPackageDocument::AUDIENCES, true), 404);

        app(EventPackageDocumentService::class)->freezeFromLive($this->record, $audience);

        Notification::make()
            ->title('Zamrożono snapshot HTML')
            ->success()
            ->send();
    }

    public function finalizePackage(string $audience): void
    {
        abort_unless(in_array($audience, EventPackageDocument::AUDIENCES, true), 404);

        app(EventPackageDocumentService::class)->finalize($this->record, $audience);

        Notification::make()
            ->title('Pakiet oznaczony jako gotowy')
            ->success()
            ->send();
    }

    public function markOfferSent(int $documentId): void
    {
        $document = EventDocument::query()
            ->where('event_id', $this->record->id)
            ->whereKey($documentId)
            ->where('is_offer', true)
            ->firstOrFail();

        $document->update([
            'offer_status' => 'sent',
            'offer_sent_at' => now(),
        ]);

        Notification::make()->title('Oferta oznaczona jako wysłana')->success()->send();
    }

    protected function makeEditPackageAction(): Actions\Action
    {
        return Actions\Action::make('editPackage')
            ->label('Edytuj pakiet')
            ->modalHeading(fn (): string => 'Edycja: '.(
                EventPackageDocument::$audienceLabels[$this->packageActionAudience ?? ''] ?? 'pakiet'
            ))
            ->modalWidth('3xl')
            ->fillForm(function (): array {
                $audience = $this->packageActionAudience;
                abort_unless($audience && in_array($audience, EventPackageDocument::AUDIENCES, true), 404);

                $package = app(EventPackageDocumentService::class)->getOrCreate($this->record, $audience);
                $overrides = $package->normalizedOverrides();

                return [
                    'edit_mode' => $package->edit_mode ?: EventPackageDocument::EDIT_OVERRIDES,
                    'status' => $package->status ?: EventPackageDocument::STATUS_DRAFT,
                    'intro_html' => $overrides['intro_html'],
                    'extra_notes_html' => $overrides['extra_notes_html'],
                    'hide_sections' => $overrides['hide_sections'],
                    'frozen_html' => $package->frozen_html,
                    'upload_path' => $package->upload_path,
                    'notes' => $package->notes,
                ];
            })
            ->form([
                Forms\Components\Select::make('edit_mode')
                    ->label('Tryb')
                    ->options(EventPackageDocument::$editModeLabels)
                    ->required()
                    ->live(),
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options(EventPackageDocument::$statusLabels)
                    ->required(),
                \FilamentTiptapEditor\TiptapEditor::make('intro_html')
                    ->label('Wstęp / uwagi na początku PDF')
                    ->visible(fn (Forms\Get $get): bool => in_array(
                        $get('edit_mode'),
                        [
                            EventPackageDocument::EDIT_OVERRIDES,
                            EventPackageDocument::EDIT_LIVE,
                            EventPackageDocument::EDIT_FROZEN,
                        ],
                        true
                    ))
                    ->columnSpanFull(),
                \FilamentTiptapEditor\TiptapEditor::make('extra_notes_html')
                    ->label('Dodatkowe uwagi na końcu PDF')
                    ->visible(fn (Forms\Get $get): bool => in_array(
                        $get('edit_mode'),
                        [
                            EventPackageDocument::EDIT_OVERRIDES,
                            EventPackageDocument::EDIT_LIVE,
                            EventPackageDocument::EDIT_FROZEN,
                        ],
                        true
                    ))
                    ->columnSpanFull(),
                Forms\Components\CheckboxList::make('hide_sections')
                    ->label('Ukryj sekcje')
                    ->options(EventPackageDocument::$hideSectionLabels)
                    ->columns(['default' => 1, 'md' => 2])
                    ->visible(fn (Forms\Get $get): bool => in_array(
                        $get('edit_mode'),
                        [EventPackageDocument::EDIT_OVERRIDES, EventPackageDocument::EDIT_LIVE],
                        true
                    )),
                Forms\Components\Textarea::make('frozen_html')
                    ->label('Zamrożona treść HTML')
                    ->rows(12)
                    ->visible(fn (Forms\Get $get): bool => $get('edit_mode') === EventPackageDocument::EDIT_FROZEN)
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('upload_path')
                    ->label('Własny PDF')
                    ->disk('public')
                    ->directory(fn () => 'event-package-uploads/'.$this->record->id)
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(20480)
                    ->visible(fn (Forms\Get $get): bool => $get('edit_mode') === EventPackageDocument::EDIT_UPLOAD)
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('notes')
                    ->label('Notatka wewnętrzna')
                    ->rows(2)
                    ->columnSpanFull(),
            ])
            ->action(function (array $data): void {
                $audience = $this->packageActionAudience;
                abort_unless($audience && in_array($audience, EventPackageDocument::AUDIENCES, true), 404);

                if (($data['edit_mode'] ?? null) === EventPackageDocument::EDIT_LIVE
                    && (filled($data['intro_html'] ?? null)
                        || filled($data['extra_notes_html'] ?? null)
                        || ! empty($data['hide_sections'] ?? []))
                ) {
                    $data['edit_mode'] = EventPackageDocument::EDIT_OVERRIDES;
                }

                app(EventPackageDocumentService::class)->saveDraft($this->record, $audience, $data);

                Notification::make()
                    ->title('Zapisano pakiet')
                    ->success()
                    ->send();

                $this->packageActionAudience = null;
            });
    }

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        // Primary „Dokumenty” rejestruje ManageEventContracts gdy są umowy;
        // ta strona jest landingiem tylko gdy brak umów.
        return Schema::hasTable('event_documents')
            && ! Schema::hasTable('contracts')
            && ! Schema::hasTable('event_agreements');
    }

    /**
     * @param  array<string, mixed>  $urlParameters
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(array $urlParameters = []): array
    {
        return [
            NavigationItem::make(static::getNavigationLabel())
                ->group(static::getNavigationGroup())
                ->parentItem(static::getNavigationParentItem())
                ->icon(static::getNavigationIcon())
                ->activeIcon(static::getActiveNavigationIcon())
                ->isActiveWhen(fn (): bool => collect(static::documentsRouteNames())
                    ->contains(fn (string $routeName): bool => request()->routeIs($routeName)))
                ->sort(static::getNavigationSort())
                ->badge(static::getNavigationBadge(), color: static::getNavigationBadgeColor())
                ->url(EventResource::getUrl('documents', $urlParameters)),
        ];
    }
}
