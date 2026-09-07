<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Concerns\SingleRelationManagerPage;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventDocumentsSubNavigation;
use App\Filament\Resources\EventResource\Concerns\HasEventWorkflowContext;
use App\Filament\Resources\EventResource\RelationManagers\DocumentsRelationManager;
use App\Models\EventDocument;
use App\Models\EventPackageDocument;
use App\Models\EventSettlementCost;
use App\Models\EventSettlementDocument;
use App\Services\EventPackageDocumentService;
use Filament\Actions;
use Filament\Forms;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

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

    public string $offersSort = 'created_at';

    public string $offersSortDirection = 'desc';

    public ?string $offersCreatedFrom = null;

    public ?string $offersCreatedUntil = null;

    public string $settlementSort = 'created_at';

    public string $settlementSortDirection = 'desc';

    public ?string $settlementCreatedFrom = null;

    public ?string $settlementCreatedUntil = null;

    /** @var list<string> */
    private const OFFER_SORT_COLUMNS = ['created_at', 'updated_at', 'offer_sent_at'];

    /** @var list<string> */
    private const SETTLEMENT_SORT_COLUMNS = ['created_at', 'updated_at'];

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
        $query = $this->record->documents()->where('is_offer', true);

        $this->applyCreatedAtDateFilter(
            $query,
            $this->offersCreatedFrom,
            $this->offersCreatedUntil,
        );

        $sort = in_array($this->offersSort, self::OFFER_SORT_COLUMNS, true)
            ? $this->offersSort
            : 'created_at';
        $direction = $this->offersSortDirection === 'asc' ? 'asc' : 'desc';

        return $query
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->get();
    }

    public function hasOfferDocuments(): bool
    {
        return $this->record->documents()->where('is_offer', true)->exists();
    }

    public function sortOffersBy(string $column): void
    {
        if (! in_array($column, self::OFFER_SORT_COLUMNS, true)) {
            return;
        }

        if ($this->offersSort === $column) {
            $this->offersSortDirection = $this->offersSortDirection === 'asc' ? 'desc' : 'asc';

            return;
        }

        $this->offersSort = $column;
        $this->offersSortDirection = 'desc';
    }

    public function resetOffersDateFilter(): void
    {
        $this->offersCreatedFrom = null;
        $this->offersCreatedUntil = null;
    }

    /**
     * Dokumenty rozliczenia (faktury z programu / hoteli / transportu) — osobna tabela od event_documents.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getSettlementDocumentRows(): Collection
    {
        if (! Schema::hasTable('event_settlement_documents')) {
            return collect();
        }

        $settlement = $this->record->relationLoaded('activeSettlement')
            ? $this->record->activeSettlement
            : $this->record->activeSettlement()->first();

        if (! $settlement) {
            return collect();
        }

        $query = $settlement->documents();

        $this->applyCreatedAtDateFilter(
            $query,
            $this->settlementCreatedFrom,
            $this->settlementCreatedUntil,
        );

        $sort = in_array($this->settlementSort, self::SETTLEMENT_SORT_COLUMNS, true)
            ? $this->settlementSort
            : 'created_at';
        $direction = $this->settlementSortDirection === 'asc' ? 'asc' : 'desc';

        $documents = $query
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->get();

        if ($documents->isEmpty()) {
            return collect();
        }

        $costIds = $documents
            ->flatMap(fn (EventSettlementDocument $doc) => collect($doc->linked_cost_ids ?? [])->map(fn ($id) => (int) $id))
            ->unique()
            ->values()
            ->all();

        $costsById = $costIds === []
            ? collect()
            : EventSettlementCost::query()
                ->whereIn('id', $costIds)
                ->get(['id', 'name', 'source_type', 'source_id'])
                ->keyBy('id');

        return $documents->map(function (EventSettlementDocument $doc) use ($costsById): array {
            $linkedNames = collect($doc->linked_cost_ids ?? [])
                ->map(fn ($id) => $costsById->get((int) $id)?->name)
                ->filter()
                ->unique()
                ->values()
                ->all();

            $files = collect($doc->files ?? [])
                ->filter(fn ($path) => is_string($path) && $path !== '')
                ->values()
                ->map(fn (string $path): array => [
                    'path' => $path,
                    'name' => basename($path),
                    'url' => Storage::disk('public')->url($path),
                ])
                ->all();

            $typeKey = (string) ($doc->document_type ?: 'other');

            return [
                'id' => (int) $doc->id,
                'type_label' => EventSettlementDocument::$documentTypes[$typeKey]
                    ?? EventSettlementDocument::$documentTypeBadges[$typeKey]
                    ?? 'Dokument',
                'badge_label' => EventSettlementDocument::$documentTypeBadges[$typeKey] ?? 'Plik',
                'number' => $doc->document_number,
                'vendor' => $doc->vendor_name,
                'linked_labels' => $linkedNames,
                'files' => $files,
                'created_at' => $doc->created_at?->format('d.m.Y H:i'),
                'updated_at' => $doc->updated_at?->format('d.m.Y H:i'),
            ];
        });
    }

    public function hasSettlementDocuments(): bool
    {
        if (! Schema::hasTable('event_settlement_documents')) {
            return false;
        }

        $settlement = $this->record->relationLoaded('activeSettlement')
            ? $this->record->activeSettlement
            : $this->record->activeSettlement()->first();

        return $settlement
            ? $settlement->documents()->exists()
            : false;
    }

    public function sortSettlementDocumentsBy(string $column): void
    {
        if (! in_array($column, self::SETTLEMENT_SORT_COLUMNS, true)) {
            return;
        }

        if ($this->settlementSort === $column) {
            $this->settlementSortDirection = $this->settlementSortDirection === 'asc' ? 'desc' : 'asc';

            return;
        }

        $this->settlementSort = $column;
        $this->settlementSortDirection = 'desc';
    }

    public function resetSettlementDateFilter(): void
    {
        $this->settlementCreatedFrom = null;
        $this->settlementCreatedUntil = null;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation  $query
     */
    private function applyCreatedAtDateFilter($query, ?string $from, ?string $until): void
    {
        if (filled($from)) {
            $query->whereDate('created_at', '>=', $from);
        }

        if (filled($until)) {
            $query->whereDate('created_at', '<=', $until);
        }
    }

    protected function getHeaderActions(): array
    {
        // Header Filament jest ukryty (pusty getHeading w HasEventDocumentsSubNavigation).
        // Linki operacyjne są w manage-event-documents.blade.php.
        return [];
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

    public function deleteOfferDocument(int $documentId): void
    {
        $document = EventDocument::query()
            ->where('event_id', $this->record->id)
            ->whereKey($documentId)
            ->where('is_offer', true)
            ->firstOrFail();

        // EventDocument::deleting usuwa też plik ze storage.
        $document->delete();

        Notification::make()
            ->title('Usunięto ofertę')
            ->success()
            ->send();
    }

    public function deleteSettlementDocument(int $documentId): void
    {
        abort_unless(Schema::hasTable('event_settlement_documents'), 404);

        $settlement = $this->record->activeSettlement()->first();
        abort_unless($settlement, 404);

        $document = EventSettlementDocument::query()
            ->where('settlement_id', $settlement->id)
            ->whereKey($documentId)
            ->firstOrFail();

        // EventSettlementDocument::deleting usuwa pliki ze storage.
        $document->delete();

        Notification::make()
            ->title('Usunięto dokument rozliczenia')
            ->success()
            ->send();
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
        // Primary „Dokumenty” = Pliki (oferty, pakiety PDF, załączniki).
        return Schema::hasTable('event_documents');
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
