<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Concerns\SingleRelationManagerPage;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventWorkflowContext;
use App\Filament\Resources\EventResource\RelationManagers\DocumentsRelationManager;
use Filament\Actions;
use Filament\Actions\ActionGroup;

class ManageEventDocuments extends SingleRelationManagerPage
{
    use HasEventWorkflowContext;

    protected static string $resource = EventResource::class;

    protected static ?string $navigationLabel = 'Dokumenty';

    protected static ?string $title = 'Dokumenty imprezy';

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static function relationManager(): string
    {
        return DocumentsRelationManager::class;
    }

    protected function getHeaderActions(): array
    {
        $pdfZipHint = 'Załączniki dodasz poniżej: zaznacz pakiety PDF i status „Zaakceptowany”.';

        return [
            Actions\Action::make('offer_word')
                ->label('Oferta DOCX')
                ->icon('heroicon-o-document-text')
                ->color('danger')
                ->url(fn () => route('admin.events.offer.word', ['event' => $this->record->id]))
                ->openUrlInNewTab(),

            ActionGroup::make([
                Actions\Action::make('pdf_folder')
                    ->label('Pakiet teczki')
                    ->tooltip($pdfZipHint)
                    ->url(fn () => route('admin.events.pdf', ['event' => $this->record->id, 'audience' => 'folder']))
                    ->openUrlInNewTab(),
                Actions\Action::make('pdf_all_packages')
                    ->label('Komplet (ZIP)')
                    ->url(fn () => route('admin.events.pdf', ['event' => $this->record->id, 'audience' => 'all']))
                    ->openUrlInNewTab(),
            ])
                ->label('Dokumenty PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->button(),

            Actions\Action::make('invoices_pdf')
                ->label('Wszystkie faktury (PDF)')
                ->icon('heroicon-o-document-duplicate')
                ->color('primary')
                ->tooltip('Scala faktury KSeF, dokumenty rozliczenia i pliki oznaczone jako faktura.')
                ->url(fn () => route('admin.events.invoices.pdf', ['event' => $this->record->id]))
                ->openUrlInNewTab(),
        ];
    }

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return \Illuminate\Support\Facades\Schema::hasTable('event_documents');
    }
}
